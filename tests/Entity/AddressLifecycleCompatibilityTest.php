<?php

declare(strict_types=1);

namespace Tests\Entity;

use App\Entity\AddressEntity;
use App\Entity\AddressOutboxEntity;
use PHPUnit\Framework\TestCase;

final class AddressLifecycleCompatibilityTest extends TestCase
{
    public function testAddressEntityLifecycleAliases(): void
    {
        $entity = new class extends AddressEntity {
            public function __construct()
            {
                $this->setCreatedAt(new \DateTimeImmutable('2026-05-23 14:00:00'));
                $this->setUpdatedAt(new \DateTimeImmutable('2026-05-23 14:05:00'));
                $this->setDeletedAt(new \DateTimeImmutable('2026-05-23 14:10:00'));
            }
        };

        self::assertSame('2026-05-23 14:00:00', $entity->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-23 14:05:00', $entity->updatedAt()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-23 14:05:00', $entity->modifiedAt()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-23 14:10:00', $entity->deletedAt()->format('Y-m-d H:i:s'));

        $entity->delete(new \DateTimeImmutable('2026-05-23 14:15:00'));
        self::assertSame('2026-05-23 14:15:00', $entity->deletedAt()->format('Y-m-d H:i:s'));

        $entity->restore();
        self::assertNull($entity->deletedAt());
    }

    public function testAddressOutboxLifecycleAliases(): void
    {
        $entity = new AddressOutboxEntity();
        $entity->setCreatedAt(new \DateTimeImmutable('2026-05-23 15:00:00'));
        $entity->lock('locker-1', new \DateTimeImmutable('2026-05-23 15:05:00'));

        self::assertSame('2026-05-23 15:00:00', $entity->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-23 15:05:00', $entity->lockedAt()?->format('Y-m-d H:i:s'));
        self::assertSame('locker-1', $entity->lockedBy());

        $entity->unlock();

        self::assertNull($entity->lockedAt());
        self::assertNull($entity->lockedBy());
    }
}
