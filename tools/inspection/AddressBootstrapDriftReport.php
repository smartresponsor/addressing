<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'tests/object-manager.php' => is_file($root.'/tests/object-manager.php'),
    'tests/console-application.php' => is_file($root.'/tests/console-application.php'),
    'bin/console' => is_file($root.'/bin/console'),
    'bin/address-demo-reset' => is_file($root.'/bin/address-demo-reset'),
    'tools/support/AddressRuntimeBootstrap.php' => is_file($root.'/tools/support/AddressRuntimeBootstrap.php'),
];

$objectManagerUsesRuntimeBootstrap = false;
$consoleUsesRuntimeBootstrap = false;
$demoResetUsesRuntimeBootstrap = false;

$objectManagerPath = $root.'/tests/object-manager.php';
if (is_file($objectManagerPath)) {
    $content = (string) file_get_contents($objectManagerPath);
    $objectManagerUsesRuntimeBootstrap = str_contains($content, 'AddressRuntimeBootstrap');
}

$consolePath = $root.'/tests/console-application.php';
if (is_file($consolePath)) {
    $content = (string) file_get_contents($consolePath);
    $consoleUsesRuntimeBootstrap = str_contains($content, 'AddressRuntimeBootstrap');
}

$demoResetPath = $root.'/bin/address-demo-reset';
if (is_file($demoResetPath)) {
    $content = (string) file_get_contents($demoResetPath);
    $demoResetUsesRuntimeBootstrap = str_contains($content, 'AddressRuntimeBootstrap');
}

$deprecatedCompatibilityFiles = [
    'bin/address-demo-reset-runtime' => is_file($root.'/bin/address-demo-reset-runtime'),
    'tests/object-manager.runtime.php' => is_file($root.'/tests/object-manager.runtime.php'),
    'tests/runtime-console-application.php' => is_file($root.'/tests/runtime-console-application.php'),
];

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'checks' => $checks,
    'signals' => [
        'objectManagerUsesRuntimeBootstrap' => $objectManagerUsesRuntimeBootstrap,
        'consoleUsesRuntimeBootstrap' => $consoleUsesRuntimeBootstrap,
        'demoResetUsesRuntimeBootstrap' => $demoResetUsesRuntimeBootstrap,
        'transitionLayerRetired' => !in_array(true, $deprecatedCompatibilityFiles, true),
    ],
    'deprecatedCompatibilityFiles' => $deprecatedCompatibilityFiles,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
