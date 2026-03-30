<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'bin/address-demo-reset-runtime',
    'tests/object-manager.runtime.php',
    'tests/runtime-console-application.php',
    'tools/inspection/AddressBootstrapDriftReport.php',
    'tools/inspection/AddressDeptracDriftReport.php',
    'tools/inspection/AddressLegacyRuntimeSurfaceReport.php',
    'tools/inspection/AddressCurrentNamespaceMap.php',
];

$present = [];
foreach ($files as $file) {
    $present[$file] = is_file($root.'/'.$file);
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => in_array(false, $present, true) ? 'partial' : 'ready',
    'fileCount' => count($present),
    'files' => $present,
    'purpose' => 'Wave 2 replacement-ready runtime sync layer for drift inspection and current-slice entrypoints.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
