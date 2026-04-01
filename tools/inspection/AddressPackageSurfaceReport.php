<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$composerPath = $root.'/composer.json';
$composer = is_file($composerPath) ? json_decode((string) file_get_contents($composerPath), true) : [];
$require = is_array($composer) && isset($composer['require']) && is_array($composer['require']) ? $composer['require'] : [];
$requireDev = is_array($composer) && isset($composer['require-dev']) && is_array($composer['require-dev']) ? $composer['require-dev'] : [];
$scripts = is_array($composer) && isset($composer['scripts']) && is_array($composer['scripts']) ? $composer['scripts'] : [];

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'signals' => [
        'doctrineOrmInRequire' => array_key_exists('doctrine/orm', $require),
        'doctrineOrmInRequireDev' => array_key_exists('doctrine/orm', $requireDev),
        'doctrineDataFixturesInRequireDev' => array_key_exists('doctrine/data-fixtures', $requireDev),
        'doctrineFixturesBundleInRequireDev' => array_key_exists('doctrine/doctrine-fixtures-bundle', $requireDev),
        'packageSurfaceAlignedToPdoRuntime' => !array_key_exists('doctrine/orm', $require),
        'doctrineSmokeScriptPresent' => array_key_exists('smoke:doctrine', $scripts),
        'packageSurfaceReportScriptPresent' => array_key_exists('report:package-surface', $scripts),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
