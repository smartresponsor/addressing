<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'tools/support/AddressRuntimeBootstrap.php' => is_file($root.'/tools/support/AddressRuntimeBootstrap.php'),
    'tools/inspection/AddressComposerScriptSurfaceReport.php' => is_file($root.'/tools/inspection/AddressComposerScriptSurfaceReport.php'),
    'tools/smoke/address-runtime-smoke.php' => is_file($root.'/tools/smoke/address-runtime-smoke.php'),
    'tools/smoke/address-fixture-sanity.php' => is_file($root.'/tools/smoke/address-fixture-sanity.php'),
    'tools/smoke/address-container-boot-smoke.php' => is_file($root.'/tools/smoke/address-container-boot-smoke.php'),
    'tools/smoke/address-fixture-load-smoke.php' => is_file($root.'/tools/smoke/address-fixture-load-smoke.php'),
    'tools/smoke/address-doctrine-mapping-smoke.php' => is_file($root.'/tools/smoke/address-doctrine-mapping-smoke.php'),
    'tools/smoke/address-graphql-smoke.php' => is_file($root.'/tools/smoke/address-graphql-smoke.php'),
];

$missing = array_values(array_keys(array_filter($checks, static fn (bool $ok): bool => false === $ok)));

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => [] === $missing ? 'ready' : 'incomplete',
    'checkCount' => count($checks),
    'missingCount' => count($missing),
    'missing' => $missing,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
