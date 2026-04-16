<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Console\Application;

require __DIR__.'/../tools/support/AddressRuntimeBootstrap.php';

$bootstrapClass = 'AddressRuntimeBootstrap';
if (!class_exists($bootstrapClass)) {
    throw new RuntimeException('address_runtime_bootstrap_missing');
}

$kernel = (new ReflectionMethod($bootstrapClass, 'bootKernel'))->invoke(null);
if (!$kernel instanceof App\Kernel) {
    throw new RuntimeException('address_runtime_kernel_bootstrap_failed');
}

return new Application($kernel);
