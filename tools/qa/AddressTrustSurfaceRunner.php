<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$reports = [
    'tools/inspection/AddressBootstrapDriftReport.php',
    'tools/inspection/AddressDeptracDriftReport.php',
    'tools/inspection/AddressLegacyRuntimeSurfaceReport.php',
    'tools/inspection/AddressCurrentNamespaceMap.php',
    'tools/inspection/AddressWave2SyncSummary.php',
];

$results = [];
foreach ($reports as $report) {
    $path = $root.'/'.$report;
    $results[] = [
        'file' => $report,
        'exists' => is_file($path),
    ];
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'reports' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
