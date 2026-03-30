<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

// Managed by Commanding inspection

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

if (class_exists(Dotenv::class) && file_exists(__DIR__.'/../.env')) {
    (new Dotenv())->bootEnv(__DIR__.'/../.env');
}

$config = ORMSetup::createAttributeMetadataConfiguration([], true);
$connectionParams = [
    'driver' => 'pdo_sqlite',
    'memory' => true,
];

return EntityManager::create($connectionParams, $config);
