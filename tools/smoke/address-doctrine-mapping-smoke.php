<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

$ormAvailable = interface_exists(EntityManagerInterface::class);
$addressEntityAvailable = class_exists(AddressEntity::class);
$snapshotEntityAvailable = class_exists(AddressEvidenceSnapshotEntity::class);

$addressReflection = $addressEntityAvailable ? new ReflectionClass(AddressEntity::class) : null;
$snapshotReflection = $snapshotEntityAvailable ? new ReflectionClass(AddressEvidenceSnapshotEntity::class) : null;

$addressOrmEntity = null !== $addressReflection && [] !== $addressReflection->getAttributes(ORM\Entity::class);
$snapshotOrmEntity = null !== $snapshotReflection && [] !== $snapshotReflection->getAttributes(ORM\Entity::class);

$status = ($ormAvailable && $addressOrmEntity && $snapshotOrmEntity) ? 'pass' : 'warn';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'doctrine_mapping',
    'status' => $status,
    'reason' => 'Addressing exposes Doctrine ORM entities as the canonical schema authority for Symfony host applications.',
    'doctrineOrmAvailable' => $ormAvailable,
    'addressEntityPresent' => $addressEntityAvailable,
    'addressEntityMapped' => $addressOrmEntity,
    'addressEvidenceSnapshotEntityPresent' => $snapshotEntityAvailable,
    'addressEvidenceSnapshotEntityMapped' => $snapshotOrmEntity,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
