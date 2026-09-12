<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Addressing\Service\Fixture\AddressDemoFixtureService;

require_once dirname(__DIR__).'/support/AddressRuntimeBootstrap.php';

$fixtureService = AddressRuntimeBootstrap::service(AddressDemoFixtureService::class);

$ok = $fixtureService instanceof AddressDemoFixtureService;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'fixture_sanity',
    'status' => $ok ? 'ready' : 'incomplete',
    'services' => [
        AddressDemoFixtureService::class => $fixtureService instanceof AddressDemoFixtureService,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('fixture_sanity_smoke_failed');
}
