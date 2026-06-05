<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use App\Entity\AddressIndexEntity;
use App\Entity\AddressOutboxEntity;
use App\Entity\RateLimitEntity;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Support\TestDatabase;

require_once __DIR__.'/../vendor/autoload.php';

$entityManager = TestDatabase::createInMemoryEntityManager([
    AddressEntity::class,
    AddressEvidenceSnapshotEntity::class,
    AddressOutboxEntity::class,
    AddressIndexEntity::class,
    RateLimitEntity::class,
]);

if (!$entityManager instanceof EntityManagerInterface) {
    throw new RuntimeException('address_phpstan_object_manager_bootstrap_failed');
}

return $entityManager;
