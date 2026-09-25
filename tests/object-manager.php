<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressIndexEntity;
use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\Entity\AddressRateLimitEntity;
use Tests\Support\TestDatabase;

require_once __DIR__.'/../vendor/autoload.php';

$entityManager = TestDatabase::createInMemoryEntityManager([
    AddressEntity::class,
    AddressEvidenceSnapshotEntity::class,
    AddressOutboxEntity::class,
    AddressIndexEntity::class,
    AddressRateLimitEntity::class,
]);

return $entityManager;
