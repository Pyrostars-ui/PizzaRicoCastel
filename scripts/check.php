<?php

declare(strict_types=1);

/**
 * Runner d'Oracle Qualité silencieux (Stack Symfony / FrankenPHP Enterprise).
 *
 * Exécute séquentiellement la chaîne complète de validation QA et sécurité.
 * En cas de succès complet, aucune sortie n'est émise (économie de tokens pour l'agent).
 * Au premier échec, affiche uniquement la trace de l'étape fautive et retourne son code d'erreur.
 */
$rootDir = dirname(__DIR__);
$hasTemplates = is_dir($rootDir.'/templates');

/** @var list<array{name: string, command: string}> $steps */
$steps = [
    [
        'name' => '1. Zero EOL Lifecycle Check',
        'command' => 'php scripts/check-eol.php',
    ],
    [
        'name' => '2. PHP-CS-Fixer (PSR-12 & Symfony)',
        'command' => 'php vendor/bin/php-cs-fixer fix --dry-run --diff',
    ],
    [
        'name' => '3. Twig-CS-Fixer',
        'command' => $hasTemplates ? 'php vendor/bin/twig-cs-fixer lint templates/' : 'php vendor/bin/twig-cs-fixer lint',
    ],
    [
        'name' => '4. Symfony Lint Twig',
        'command' => $hasTemplates ? 'php bin/console lint:twig templates/' : 'php bin/console lint:twig',
    ],
    [
        'name' => '5. Symfony Lint Container',
        'command' => 'php bin/console lint:container',
    ],
    [
        'name' => '6. PHPStan (Level 10 Strict)',
        'command' => 'php vendor/bin/phpstan analyse --level=10 --no-progress',
    ],
    [
        'name' => '7. Doctrine Schema Validation',
        'command' => 'php bin/console doctrine:schema:validate',
    ],
    [
        'name' => '8. AssetMapper Security Audit',
        'command' => 'php bin/console importmap:audit',
    ],
    [
        'name' => '9. Composer Security Audit',
        'command' => 'composer audit',
    ],
    [
        'name' => '10. PHPUnit Test Suite',
        'command' => 'php vendor/bin/phpunit --no-coverage',
    ],
];

foreach ($steps as $step) {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['redirect', 1],
    ];

    /** @phpstan-ignore argument.type */
    $process = proc_open($step['command'], $descriptors, $pipes, $rootDir);

    if (!is_resource($process)) {
        fwrite(\STDERR, sprintf("\n[ERREUR] Impossible de démarrer l'étape : %s (%s)\n", $step['name'], $step['command']));
        exit(1);
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $exitCode = proc_close($process);

    if (0 !== $exitCode) {
        fwrite(\STDERR, sprintf("\n❌ ÉCHEC DE L'ÉTAPE : %s\n", $step['name']));
        fwrite(\STDERR, sprintf("Commande : %s\n", $step['command']));
        fwrite(\STDERR, sprintf("Code retour : %d\n", $exitCode));
        fwrite(\STDERR, "---------------- SORTIE DÉTAILLÉE ----------------\n");
        if ('' !== $output && false !== $output) {
            fwrite(\STDERR, $output);
        }
        fwrite(\STDERR, "--------------------------------------------------\n");
        exit($exitCode);
    }
}

exit(0);
