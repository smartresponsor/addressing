<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Kernel;
use App\Service\Http\Address\AddressReadHttpService;
use Doctrine\DBAL\Connection;

require_once dirname(__DIR__).'/../support/AddressRuntimeBootstrap.php';

$kernel = AddressRuntimeBootstrap::bootKernel();
$addressReadHttpService = AddressRuntimeBootstrap::service(AddressReadHttpService::class);
$connection = AddressRuntimeBootstrap::connection();
$driver = $connection->getDatabasePlatform()->getName();

$ok = $kernel instanceof Kernel
    && $addressReadHttpService instanceof AddressReadHttpService
    && $connection instanceof Connection
    && '' !== $driver;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'runtime',
    'status' => $ok ? 'ready' : 'incomplete',
    'driver' => $driver,
    'services' => [
        Kernel::class => $kernel instanceof Kernel,
        AddressReadHttpService::class => $addressReadHttpService instanceof AddressReadHttpService,
        Connection::class => $connection instanceof Connection,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('runtime_smoke_failed');
}
