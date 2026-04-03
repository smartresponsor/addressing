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
    'tools/inspection/AddressPersistenceWriteSurfaceReport.php',
];

$allowedStatuses = ['ready', 'report', 'reference'];
$results = [];
$overallStatus = 'ready';

foreach ($reports as $report) {
    $path = $root.'/'.$report;
    if (!is_file($path)) {
        $results[] = [
            'file' => $report,
            'exists' => false,
            'status' => 'missing',
        ];
        $overallStatus = 'partial';
        continue;
    }

    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($path);
    $outputLines = [];
    $exitCode = 0;
    exec($command.' 2>&1', $outputLines, $exitCode);
    $output = implode("\n", $outputLines);
    $decoded = json_decode($output, true);
    $status = is_array($decoded) && isset($decoded['status']) && is_string($decoded['status'])
        ? $decoded['status']
        : 'invalid';
    $isReady = $exitCode === 0 && in_array($status, $allowedStatuses, true);

    if (!$isReady) {
        $overallStatus = 'partial';
    }

    $results[] = [
        'file' => $report,
        'exists' => true,
        'exitCode' => $exitCode,
        'status' => $status,
        'rawOutput' => is_array($decoded) ? null : $output,
    ];
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => $overallStatus,
    'reports' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
