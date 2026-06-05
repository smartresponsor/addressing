<?php

declare(strict_types=1);

namespace Tests\Entity;

use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\TestCase;

final class AddressDoctrineAuthoritySurfaceTest extends TestCase
{
    public function testAddressEntityIsMappedAsDoctrineEntity(): void
    {
        $reflection = new \ReflectionClass(AddressEntity::class);

        self::assertNotEmpty($reflection->getAttributes(ORM\Entity::class));
        self::assertNotEmpty($reflection->getAttributes(ORM\Table::class));
    }

    public function testAddressEvidenceSnapshotEntityIsMappedAsDoctrineEntity(): void
    {
        $reflection = new \ReflectionClass(AddressEvidenceSnapshotEntity::class);

        self::assertNotEmpty($reflection->getAttributes(ORM\Entity::class));
        self::assertNotEmpty($reflection->getAttributes(ORM\Table::class));
    }
}
