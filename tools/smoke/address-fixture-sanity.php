<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Service\Fixture\AddressDemoFixtureService;
use App\Http\Dto\AddressInputFactory;
use App\Service\Application\AddressReadService;
use App\Service\Application\AddressWriteService;

require_once dirname(__DIR__).'/../support/AddressRuntimeBootstrap.php';

$fixtureService = AddressRuntimeBootstrap::service(AddressDemoFixtureService::class);
$addressReadService = AddressRuntimeBootstrap::service(AddressReadService::class);
$addressWriteService = AddressRuntimeBootstrap::service(AddressWriteService::class);
$inputFactory = AddressRuntimeBootstrap::service(AddressInputFactory::class);

$ok = $fixtureService instanceof AddressDemoFixtureService
    && $addressReadService instanceof AddressReadService
    && $addressWriteService instanceof AddressWriteService
    && $inputFactory instanceof AddressInputFactory;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'fixture_sanity',
    'status' => $ok ? 'ready' : 'incomplete',
    'services' => [
        AddressDemoFixtureService::class => $fixtureService instanceof AddressDemoFixtureService,
        AddressReadService::class => $addressReadService instanceof AddressReadService,
        AddressWriteService::class => $addressWriteService instanceof AddressWriteService,
        AddressInputFactory::class => $inputFactory instanceof AddressInputFactory,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('fixture_sanity_smoke_failed');
}
