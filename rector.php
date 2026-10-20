<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

$rectorConfig = RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        symfonyCodeQuality: true,
        doctrineCodeQuality: true,
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
        phpunit: true,
    )
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true,
    );

$rectorConfig->withComposerBased(
    twig: true,
    doctrine: true,
    phpunit: true,
    symfony: true,
);

return $rectorConfig;
