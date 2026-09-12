<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Addressing\Kernel;
use App\Addressing\Service\Http\Address\AddressReadHttpService;

require_once dirname(__DIR__).'/support/AddressRuntimeBootstrap.php';

$kernel = AddressRuntimeBootstrap::bootKernel();
$addressReadHttpService = AddressRuntimeBootstrap::service(AddressReadHttpService::class);

$ok = $kernel instanceof Kernel
    && $addressReadHttpService instanceof AddressReadHttpService;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'runtime',
    'status' => $ok ? 'ready' : 'incomplete',
    'services' => [
        Kernel::class => $kernel instanceof Kernel,
        AddressReadHttpService::class => $addressReadHttpService instanceof AddressReadHttpService,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('runtime_smoke_failed');
}
