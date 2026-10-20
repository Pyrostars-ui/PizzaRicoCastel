<?php

declare(strict_types=1);

/**
 * Vérificateur dynamique de cycle de vie (Zero EOL).
 *
 * Interroge https://endoflife.date/api/php.json et https://endoflife.date/api/symfony.json
 * pour garantir que le runtime PHP et le framework Symfony ciblent exclusivement
 * des branches en support actif (PHP 8.4/8.5+, Symfony 7.2+/8.x non-EOL).
 */
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

/**
 * @return list<array<string, mixed>>
 */
function fetchEolCycleData(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'header' => "User-Agent: Symfony-Stack-Enterprise-EOL-Checker/1.0\r\nAccept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (false === $raw || '' === $raw) {
        throw new RuntimeException(sprintf('Impossible de contacter %s', $url));
    }

    $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('Réponse JSON invalide depuis %s', $url));
    }

    /* @var list<array<string, mixed>> $decoded */
    return array_values($decoded);
}

/**
 * @param list<array<string, mixed>> $cycles
 *
 * @return array<string, mixed>|null
 */
function findCycle(array $cycles, string $cycleName): ?array
{
    foreach ($cycles as $entry) {
        if (isset($entry['cycle']) && is_scalar($entry['cycle']) && (string) $entry['cycle'] === $cycleName) {
            return $entry;
        }
    }

    return null;
}

function isDateFutureOrToday(mixed $dateValue, string $today): bool
{
    if (is_bool($dateValue)) {
        return !$dateValue;
    }
    if (!is_string($dateValue) || '' === $dateValue) {
        return false;
    }

    return $dateValue >= $today;
}

$phpCycle = \PHP_MAJOR_VERSION.'.'.\PHP_MINOR_VERSION;
$composerPhpCycle = null;
$symfonyCycle = null;

$envComposerPath = getenv('COMPOSER_JSON_PATH');
$composerJsonPath = is_string($envComposerPath) && '' !== $envComposerPath ? $envComposerPath : dirname(__DIR__).'/composer.json';
if (is_file($composerJsonPath)) {
    $composerContent = (string) file_get_contents($composerJsonPath);
    $decodedData = json_decode($composerContent, true);
    /** @var array<string, mixed> $composerData */
    $composerData = is_array($decodedData) ? $decodedData : [];
    $require = is_array($composerData['require'] ?? null) ? $composerData['require'] : [];

    if (isset($require['php']) && is_string($require['php'])) {
        if (1 === preg_match('/(\d+\.\d+)/', $require['php'], $phpMatches)) {
            $composerPhpCycle = $phpMatches[1];
        }
    }

    foreach (['symfony/framework-bundle', 'symfony/http-kernel', 'symfony/console', 'symfony/runtime'] as $pkg) {
        if (isset($require[$pkg]) && is_string($require[$pkg])) {
            if (1 === preg_match('/(\d+\.\d+)/', $require[$pkg], $matches)) {
                $symfonyCycle = $matches[1];
                break;
            }
        }
    }

    if (null === $symfonyCycle && isset($composerData['extra']) && is_array($composerData['extra'])) {
        $sfExtra = $composerData['extra']['symfony'] ?? null;
        if (is_array($sfExtra) && isset($sfExtra['require']) && is_string($sfExtra['require'])) {
            if (1 === preg_match('/(\d+\.\d+)/', $sfExtra['require'], $matches)) {
                $symfonyCycle = $matches[1];
            }
        }
    }
}

if (version_compare($phpCycle, '8.4', '<')) {
    fwrite(\STDERR, sprintf("❌ [EOL Check] Runtime PHP %s interdit (< 8.4). Ciblez PHP 8.4 ou 8.5.\n", $phpCycle));
    exit(1);
}

if (null !== $composerPhpCycle && version_compare($composerPhpCycle, '8.4', '<')) {
    fwrite(\STDERR, sprintf("❌ [EOL Check] Contrainte composer.json 'php' (%s) cible une version EOL (< 8.4). Ciblez ^8.4 ou ^8.5.\n", $composerPhpCycle));
    exit(1);
}

if (null !== $symfonyCycle && version_compare($symfonyCycle, '7.2', '<')) {
    fwrite(\STDERR, sprintf("❌ [EOL Check] Symfony %s est EOL (< 7.2). Ciblez une branche active (7.4 LTS / 8.x).\n", $symfonyCycle));
    exit(1);
}

try {
    $envPhpUrl = getenv('EOL_PHP_API_URL');
    $phpUrl = is_string($envPhpUrl) && '' !== $envPhpUrl ? $envPhpUrl : 'https://endoflife.date/api/php.json';
    $envSfUrl = getenv('EOL_SYMFONY_API_URL');
    $sfUrl = is_string($envSfUrl) && '' !== $envSfUrl ? $envSfUrl : 'https://endoflife.date/api/symfony.json';
    $phpCycles = fetchEolCycleData($phpUrl);
    $symfonyCycles = fetchEolCycleData($sfUrl);
} catch (Throwable $e) {
    fwrite(\STDERR, sprintf("⚠️ [EOL Check] Vérification réseau indisponible (%s). Plancher minimal local validé.\n", $e->getMessage()));
    exit(0);
}

foreach (array_unique(array_filter([$phpCycle, $composerPhpCycle])) as $targetPhpCycle) {
    $phpInfo = findCycle($phpCycles, $targetPhpCycle);
    if (null === $phpInfo) {
        fwrite(\STDERR, sprintf("❌ [EOL Check] Cycle PHP %s introuvable dans endoflife.date.\n", $targetPhpCycle));
        exit(1);
    }

    $phpActiveSupport = isDateFutureOrToday($phpInfo['support'] ?? false, $today);
    $phpNotEol = isDateFutureOrToday($phpInfo['eol'] ?? false, $today);

    if (!$phpNotEol || !$phpActiveSupport) {
        fwrite(
            \STDERR,
            sprintf(
                "❌ [EOL Check] PHP %s n'est pas en support actif autorisé (support: %s, eol: %s). Ciblez PHP 8.4 ou 8.5.\n",
                $targetPhpCycle,
                is_scalar($phpInfo['support'] ?? null) ? (string) $phpInfo['support'] : 'N/A',
                is_scalar($phpInfo['eol'] ?? null) ? (string) $phpInfo['eol'] : 'N/A'
            )
        );
        exit(1);
    }
}

$phpInfo = findCycle($phpCycles, $phpCycle);

if (null !== $symfonyCycle) {
    $sfInfo = findCycle($symfonyCycles, $symfonyCycle);
    if (null === $sfInfo) {
        fwrite(\STDERR, sprintf("❌ [EOL Check] Cycle Symfony %s introuvable dans endoflife.date.\n", $symfonyCycle));
        exit(1);
    }

    $sfActiveSupport = isDateFutureOrToday($sfInfo['support'] ?? false, $today);
    $sfNotEol = isDateFutureOrToday($sfInfo['eol'] ?? false, $today);

    if (!$sfNotEol || !$sfActiveSupport) {
        fwrite(
            \STDERR,
            sprintf(
                "❌ [EOL Check] Symfony %s est EOL ou hors support actif (support: %s, eol: %s). Ciblez une branche active (7.4 LTS / 8.x).\n",
                $symfonyCycle,
                is_scalar($sfInfo['support'] ?? null) ? (string) $sfInfo['support'] : 'N/A',
                is_scalar($sfInfo['eol'] ?? null) ? (string) $sfInfo['eol'] : 'N/A'
            )
        );
        exit(1);
    }
}

fwrite(
    \STDOUT,
    sprintf(
        "✅ [EOL Check] Conformité Zero EOL validée le %s (PHP %s actif jusqu'au %s%s).\n",
        $today,
        $phpCycle,
        is_scalar($phpInfo['support'] ?? null) ? (string) $phpInfo['support'] : 'N/A',
        null !== $symfonyCycle ? sprintf(', Symfony %s', $symfonyCycle) : ''
    )
);
exit(0);
