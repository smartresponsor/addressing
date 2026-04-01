<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Fixture\AddressDemoFixtureService;
use App\Http\Dto\AddressInputFactory;
use App\Service\Application\AddressService;

require_once dirname(__DIR__).'/../support/AddressRuntimeBootstrap.php';

$fixtureService = AddressRuntimeBootstrap::service(AddressDemoFixtureService::class);
$addressService = AddressRuntimeBootstrap::service(AddressService::class);
$inputFactory = AddressRuntimeBootstrap::service(AddressInputFactory::class);

$ok = $fixtureService instanceof AddressDemoFixtureService
    && $addressService instanceof AddressService
    && $inputFactory instanceof AddressInputFactory;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'fixture_sanity',
    'status' => $ok ? 'ready' : 'incomplete',
    'services' => [
        AddressDemoFixtureService::class => $fixtureService instanceof AddressDemoFixtureService,
        AddressService::class => $addressService instanceof AddressService,
        AddressInputFactory::class => $inputFactory instanceof AddressInputFactory,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('fixture_sanity_smoke_failed');
}
