<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Http\Controller\AddressController;
use App\Kernel;

require_once dirname(__DIR__).'/../support/AddressRuntimeBootstrap.php';

$kernel = AddressRuntimeBootstrap::bootKernel();
$controller = AddressRuntimeBootstrap::service(AddressController::class);
$pdo = AddressRuntimeBootstrap::pdo();
$driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

$ok = $kernel instanceof Kernel
    && $controller instanceof AddressController
    && $pdo instanceof \PDO
    && is_string($driver)
    && '' !== $driver;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'runtime',
    'status' => $ok ? 'ready' : 'incomplete',
    'driver' => $driver,
    'services' => [
        Kernel::class => $kernel instanceof Kernel,
        AddressController::class => $controller instanceof AddressController,
        \PDO::class => $pdo instanceof \PDO,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('runtime_smoke_failed');
}
