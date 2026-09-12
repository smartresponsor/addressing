<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Addressing\Service\Http\Address\AddressManageHttpService;
use App\Addressing\Service\Http\Address\AddressReadHttpService;
use App\Addressing\Service\Http\Address\AddressWriteHttpService;

require_once dirname(__DIR__).'/support/AddressRuntimeBootstrap.php';

$addressManageHttpService = AddressRuntimeBootstrap::service(AddressManageHttpService::class);
$addressWriteHttpService = AddressRuntimeBootstrap::service(AddressWriteHttpService::class);
$addressReadHttpService = AddressRuntimeBootstrap::service(AddressReadHttpService::class);

$ok = $addressManageHttpService instanceof AddressManageHttpService
    && $addressWriteHttpService instanceof AddressWriteHttpService
    && $addressReadHttpService instanceof AddressReadHttpService;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'container_boot',
    'status' => $ok ? 'ready' : 'incomplete',
    'services' => [
        AddressManageHttpService::class => $addressManageHttpService instanceof AddressManageHttpService,
        AddressWriteHttpService::class => $addressWriteHttpService instanceof AddressWriteHttpService,
        AddressReadHttpService::class => $addressReadHttpService instanceof AddressReadHttpService,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('container_boot_smoke_failed');
}
