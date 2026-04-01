<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Fixture\AddressDemoFixtureService;

require_once dirname(__DIR__).'/../support/AddressRuntimeBootstrap.php';

$count = isset($argv[1]) && is_numeric($argv[1]) ? max(1, (int) $argv[1]) : 1;

/** @var AddressDemoFixtureService $fixtureService */
$fixtureService = AddressRuntimeBootstrap::service(AddressDemoFixtureService::class);
$loaded = $fixtureService->resetAndLoad($count);

$pdo = AddressRuntimeBootstrap::pdo();
$rowCount = (int) $pdo->query('SELECT COUNT(*) FROM address_entity')->fetchColumn();

$ok = $loaded === $count && $rowCount === $count;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'fixture_load',
    'status' => $ok ? 'ready' : 'incomplete',
    'requested' => $count,
    'loaded' => $loaded,
    'rowCount' => $rowCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('fixture_load_smoke_failed');
}
