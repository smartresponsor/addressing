<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

require __DIR__.'/../tools/support/AddressRuntimeBootstrap.php';

$bootstrapClass = 'AddressRuntimeBootstrap';
if (!class_exists($bootstrapClass)) {
    throw new RuntimeException('address_runtime_bootstrap_missing');
}

$pdo = (new ReflectionMethod($bootstrapClass, 'pdo'))->invoke(null);
if (!$pdo instanceof PDO) {
    throw new RuntimeException('address_runtime_pdo_bootstrap_failed');
}

return $pdo;
