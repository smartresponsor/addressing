<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Service\Http\Address\AddressManageHttpService;
use App\Service\Http\Address\AddressReadHttpService;
use App\Service\Http\Address\AddressWriteHttpService;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

require_once dirname(__DIR__).'/../support/AddressRuntimeBootstrap.php';

$formFactory = AddressRuntimeBootstrap::service(FormFactoryInterface::class);
$twig = AddressRuntimeBootstrap::service(Environment::class);
$addressManageHttpService = AddressRuntimeBootstrap::service(AddressManageHttpService::class);
$addressWriteHttpService = AddressRuntimeBootstrap::service(AddressWriteHttpService::class);
$addressReadHttpService = AddressRuntimeBootstrap::service(AddressReadHttpService::class);

$ok = $formFactory instanceof FormFactoryInterface
    && $twig instanceof Environment
    && $addressManageHttpService instanceof AddressManageHttpService
    && $addressWriteHttpService instanceof AddressWriteHttpService
    && $addressReadHttpService instanceof AddressReadHttpService;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'container_boot',
    'status' => $ok ? 'ready' : 'incomplete',
    'services' => [
        FormFactoryInterface::class => $formFactory instanceof FormFactoryInterface,
        Environment::class => $twig instanceof Environment,
        AddressManageHttpService::class => $addressManageHttpService instanceof AddressManageHttpService,
        AddressWriteHttpService::class => $addressWriteHttpService instanceof AddressWriteHttpService,
        AddressReadHttpService::class => $addressReadHttpService instanceof AddressReadHttpService,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('container_boot_smoke_failed');
}
