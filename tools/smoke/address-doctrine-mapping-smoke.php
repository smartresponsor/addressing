<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressOutboxEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

$ormAvailable = interface_exists(EntityManagerInterface::class);
$addressEntityAvailable = class_exists(AddressEntity::class);
$snapshotEntityAvailable = class_exists(AddressEvidenceSnapshotEntity::class);
$outboxEntityAvailable = class_exists(AddressOutboxEntity::class);

$addressReflection = $addressEntityAvailable ? new ReflectionClass(AddressEntity::class) : null;
$snapshotReflection = $snapshotEntityAvailable ? new ReflectionClass(AddressEvidenceSnapshotEntity::class) : null;
$outboxReflection = $outboxEntityAvailable ? new ReflectionClass(AddressOutboxEntity::class) : null;

$addressOrmEntity = null !== $addressReflection && [] !== $addressReflection->getAttributes(ORM\Entity::class);
$snapshotOrmEntity = null !== $snapshotReflection && [] !== $snapshotReflection->getAttributes(ORM\Entity::class);
$outboxOrmEntity = null !== $outboxReflection && [] !== $outboxReflection->getAttributes(ORM\Entity::class);

$ready = $ormAvailable && $addressOrmEntity && $snapshotOrmEntity && $outboxOrmEntity;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'doctrine_mapping',
    'status' => $ready ? 'ready' : 'incomplete',
    'reason' => 'Addressing Doctrine ORM entities are the executable schema authority for the standalone runtime.',
    'doctrineOrmAvailable' => $ormAvailable,
    'addressEntityPresent' => $addressEntityAvailable,
    'addressEntityMapped' => $addressOrmEntity,
    'addressEvidenceSnapshotEntityPresent' => $snapshotEntityAvailable,
    'addressEvidenceSnapshotEntityMapped' => $snapshotOrmEntity,
    'addressOutboxEntityPresent' => $outboxEntityAvailable,
    'addressOutboxEntityMapped' => $outboxOrmEntity,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ready) {
    throw new RuntimeException('doctrine_mapping_smoke_failed');
}
