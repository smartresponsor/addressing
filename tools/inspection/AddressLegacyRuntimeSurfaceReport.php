<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$composerPath = $root.'/composer.json';
$composer = is_file($composerPath) ? json_decode((string) file_get_contents($composerPath), true) : [];
$scripts = is_array($composer) && isset($composer['scripts']) && is_array($composer['scripts']) ? $composer['scripts'] : [];

$interesting = [
    'smoke:runtime',
    'smoke:fixtures',
    'smoke:container',
    'smoke:doctrine',
    'smoke:fixture-load',
    'smoke:graphql',
    'report:runtime-proof',
    'fixtures:demo',
];

$reported = [];
foreach ($interesting as $name) {
    $reported[$name] = $scripts[$name] ?? null;
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'scriptSurface' => $reported,
    'runtimeReplacements' => [
        'bin/address-demo-reset-runtime' => is_file($root.'/bin/address-demo-reset-runtime'),
        'tests/object-manager.runtime.php' => is_file($root.'/tests/object-manager.runtime.php'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
