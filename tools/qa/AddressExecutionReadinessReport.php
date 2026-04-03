<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'host_readiness' => 'tools/smoke/category-host-readiness-smoke.php',
    'runtime_tree' => 'tools/smoke/address-runtime-smoke.php',
    'container_boot' => 'tools/smoke/category-container-boot-smoke.php',
    'runtime' => 'tools/smoke/category-runtime-smoke.php',
    'fixture_sanity' => 'tools/smoke/category-fixture-sanity.php',
    'fixture_load' => 'tools/smoke/category-fixture-load-smoke.php 1',
    'trust_surface' => 'tools/qa/AddressTrustSurfaceRunner.php',
    'dry_run_server' => 'tools/e2e/dry-run-server.php',
];

$phpBinary = PHP_BINARY;
$results = [];
$overallStatus = 'ready';

foreach ($checks as $name => $commandSuffix) {
    $command = sprintf('%s %s/%s', escapeshellarg($phpBinary), escapeshellarg($root), $commandSuffix);
    $outputLines = [];
    $exitCode = 0;
    exec($command.' 2>&1', $outputLines, $exitCode);
    $output = implode("\n", $outputLines);
    $decoded = json_decode($output, true);
    $status = is_array($decoded) && isset($decoded['status']) && is_string($decoded['status'])
        ? $decoded['status']
        : ($exitCode === 0 ? 'ready' : 'failed');

    if (in_array($status, ['blocked', 'failed', 'incomplete', 'partial', 'fail'], true)) {
        $overallStatus = 'blocked';
    }

    $results[$name] = [
        'command' => trim($commandSuffix),
        'exitCode' => $exitCode,
        'status' => $status,
        'payload' => $decoded,
        'rawOutput' => $decoded === null ? $output : null,
    ];
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => $overallStatus,
    'checks' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
