<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;

$available = interface_exists(EntityManagerInterface::class);

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'doctrine_mapping',
    'status' => 'not_applicable',
    'reason' => 'Current Addressing runtime is PDO-first. Doctrine ORM, when installed, is a development-only footprint and is not part of the runtime contract or Symfony container surface.',
    'doctrineOrmAvailableInDevContext' => $available,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
