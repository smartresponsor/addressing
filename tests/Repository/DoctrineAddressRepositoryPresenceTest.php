<?php

declare(strict_types=1);

namespace Tests\Repository;

use PHPUnit\Framework\TestCase;

final class DoctrineAddressRepositoryPresenceTest extends TestCase
{
    public function testLegacyCompositeDoctrineRepositoryRemainsRetired(): void
    {
        self::assertFalse(class_exists('App\\Addressing\\Repository\\DoctrineAddressRepository'));
    }
}
