<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$composerPath = $root.'/composer.json';
$composer = is_file($composerPath) ? json_decode((string) file_get_contents($composerPath), true) : [];
$scripts = is_array($composer) && isset($composer['scripts']) && is_array($composer['scripts']) ? $composer['scripts'] : [];
$require = is_array($composer) && isset($composer['require']) && is_array($composer['require']) ? $composer['require'] : [];
$requireDev = is_array($composer) && isset($composer['require-dev']) && is_array($composer['require-dev']) ? $composer['require-dev'] : [];

$interesting = [
    'smoke:runtime',
    'smoke:fixtures',
    'smoke:container',
    'smoke:doctrine',
    'smoke:fixture-load',
    'smoke:graphql',
    'report:runtime-proof',
    'report:runtime-sync',
    'report:package-surface',
    'qa:trust-surface',
    'fixtures:demo',
];

$reported = [];
foreach ($interesting as $name) {
    $reported[$name] = $scripts[$name] ?? null;
}

$transitionLayer = [
    'bin/address-demo-reset-runtime' => is_file($root.'/bin/address-demo-reset-runtime'),
    'tests/object-manager.runtime.php' => is_file($root.'/tests/object-manager.runtime.php'),
    'tests/runtime-console-application.php' => is_file($root.'/tests/runtime-console-application.php'),
    'tools/inspection/AddressWave2SyncSummary.php' => is_file($root.'/tools/inspection/AddressWave2SyncSummary.php'),
];

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'scriptSurface' => $reported,
    'packageSignals' => [
        'doctrineOrmInRequire' => array_key_exists('doctrine/orm', $require),
        'doctrineOrmInRequireDev' => array_key_exists('doctrine/orm', $requireDev),
        'packageSurfaceAlignedToPdoRuntime' => !array_key_exists('doctrine/orm', $require),
    ],
    'transitionLayer' => $transitionLayer,
    'transitionLayerRetired' => !in_array(true, $transitionLayer, true),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
