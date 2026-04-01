<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'composer.json',
    'tests/object-manager.php',
    'tests/console-application.php',
    'bin/address-demo-reset',
    'bin/console',
    'config/addressing_deptrac.yaml',
    'docs/test-environments.md',
    'tools/inspection/AddressRuntimeProofReport.php',
    'tools/inspection/AddressBootstrapDriftReport.php',
    'tools/inspection/AddressLegacyRuntimeSurfaceReport.php',
    'tools/qa/AddressTrustSurfaceRunner.php',
];

$present = [];
foreach ($files as $file) {
    $present[$file] = is_file($root.'/'.$file);
}

$transitionLayer = [
    'bin/address-demo-reset-runtime' => is_file($root.'/bin/address-demo-reset-runtime'),
    'tests/object-manager.runtime.php' => is_file($root.'/tests/object-manager.runtime.php'),
    'tests/runtime-console-application.php' => is_file($root.'/tests/runtime-console-application.php'),
    'tools/inspection/AddressWave2SyncSummary.php' => is_file($root.'/tools/inspection/AddressWave2SyncSummary.php'),
];

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => in_array(false, $present, true) ? 'partial' : 'ready',
    'fileCount' => count($present),
    'files' => $present,
    'transitionLayer' => $transitionLayer,
    'transitionLayerRetired' => !in_array(true, $transitionLayer, true),
    'purpose' => 'Current runtime sync surface after retiring temporary compatibility files.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
