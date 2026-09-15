<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root.'/tools/inspection/AddressRouteInventoryReport.php';

$reports = [
    'tools/inspection/AddressRouteInventoryReport.php',
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

$results = [];
foreach ($reports as $report) {
    $path = $root.'/'.$report;
    $results[] = [
        'file' => $report,
        'exists' => is_file($path),
    ];
}

$routeSource = file_get_contents($root.'/public/index.php');
$routeInventory = false === $routeSource
    ? ['method_tokens' => [], 'uri_tokens' => [], 'uri_patterns' => []]
    : addressRouteInventory($routeSource);
$routeInventoryReady = [] === array_diff(['DELETE', 'GET', 'PATCH', 'POST'], $routeInventory['method_tokens'])
    && in_array('/address/manage', $routeInventory['uri_tokens'], true)
    && in_array('/api/address', $routeInventory['uri_tokens'], true)
    && 3 <= count($routeInventory['uri_patterns']);
$reportsReady = !in_array(false, array_column($results, 'exists'), true);

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => $reportsReady && $routeInventoryReady ? 'ready' : 'partial',
    'route_inventory_ready' => $routeInventoryReady,
    'route_inventory' => $routeInventory,
    'reports' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
