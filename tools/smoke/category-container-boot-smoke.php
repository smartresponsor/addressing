<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

require_once dirname(__DIR__).'/support/AddressRuntimeBootstrap.php';

$kernel = AddressRuntimeBootstrap::bootKernel();
$router = AddressRuntimeBootstrap::service('router');
$httpKernel = AddressRuntimeBootstrap::service('http_kernel');

$ok = $kernel instanceof Kernel
    && $router instanceof RouterInterface
    && $httpKernel instanceof HttpKernelInterface;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'container_boot',
    'status' => $ok ? 'ready' : 'incomplete',
    'services' => [
        Kernel::class => $kernel instanceof Kernel,
        RouterInterface::class => $router instanceof RouterInterface,
        HttpKernelInterface::class => $httpKernel instanceof HttpKernelInterface,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('container_boot_smoke_failed');
}
