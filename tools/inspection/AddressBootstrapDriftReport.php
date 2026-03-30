<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'tests/object-manager.php' => is_file($root.'/tests/object-manager.php'),
    'tests/object-manager.runtime.php' => is_file($root.'/tests/object-manager.runtime.php'),
    'tests/console-application.php' => is_file($root.'/tests/console-application.php'),
    'bin/console' => is_file($root.'/bin/console'),
    'bin/address-demo-reset' => is_file($root.'/bin/address-demo-reset'),
    'bin/address-demo-reset-runtime' => is_file($root.'/bin/address-demo-reset-runtime'),
    'tools/support/AddressRuntimeBootstrap.php' => is_file($root.'/tools/support/AddressRuntimeBootstrap.php'),
];

$objectManagerMentionsDoctrine = false;
$legacyDemoResetDirectFixture = false;

$objectManagerPath = $root.'/tests/object-manager.php';
if (is_file($objectManagerPath)) {
    $content = (string) file_get_contents($objectManagerPath);
    $objectManagerMentionsDoctrine = str_contains($content, "get('doctrine')") || str_contains($content, 'get("doctrine")');
}

$demoResetPath = $root.'/bin/address-demo-reset';
if (is_file($demoResetPath)) {
    $content = (string) file_get_contents($demoResetPath);
    $legacyDemoResetDirectFixture = str_contains($content, 'new AddressDemoFixtureService(');
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'checks' => $checks,
    'signals' => [
        'legacyObjectManagerMentionsDoctrineService' => $objectManagerMentionsDoctrine,
        'legacyDemoResetDirectFixtureConstruction' => $legacyDemoResetDirectFixture,
        'runtimeBootstrapAvailable' => is_file($root.'/tools/support/AddressRuntimeBootstrap.php'),
        'runtimeObjectManagerAvailable' => is_file($root.'/tests/object-manager.runtime.php'),
        'runtimeDemoResetAvailable' => is_file($root.'/bin/address-demo-reset-runtime'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
