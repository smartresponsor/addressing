<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Http\Controller\AddressController;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

require_once dirname(__DIR__).'/../support/AddressRuntimeBootstrap.php';

$formFactory = AddressRuntimeBootstrap::service(FormFactoryInterface::class);
$twig = AddressRuntimeBootstrap::service(Environment::class);
$controller = AddressRuntimeBootstrap::service(AddressController::class);

$ok = $formFactory instanceof FormFactoryInterface
    && $twig instanceof Environment
    && $controller instanceof AddressController;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'container_boot',
    'status' => $ok ? 'ready' : 'incomplete',
    'services' => [
        FormFactoryInterface::class => $formFactory instanceof FormFactoryInterface,
        Environment::class => $twig instanceof Environment,
        AddressController::class => $controller instanceof AddressController,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('container_boot_smoke_failed');
}
