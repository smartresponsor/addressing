<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$reports = [
    'tools/inspection/AddressBootstrapDriftReport.php',
    'tools/inspection/AddressDeptracDriftReport.php',
    'tools/inspection/AddressLegacyRuntimeSurfaceReport.php',
    'tools/inspection/AddressCurrentNamespaceMap.php',
    'tools/inspection/AddressRuntimeSyncSummary.php',
    'tools/inspection/AddressRuntimeProofReport.php',
    'tools/inspection/AddressComposerScriptSurfaceReport.php',
    'tools/inspection/AddressTestSupportSurfaceReport.php',
    'tools/inspection/AddressPackageSurfaceReport.php',
    'tools/inspection/AddressApplicationSurfaceReport.php',
    'tools/inspection/AddressValidatedApplierSurfaceReport.php',
    'tools/inspection/AddressValidatedMutationPlanSurfaceReport.php',
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
    'status' => in_array(false, array_column($results, 'exists'), true) ? 'partial' : 'ready',
    'reports' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
