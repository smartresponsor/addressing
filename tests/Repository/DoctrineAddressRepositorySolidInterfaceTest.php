<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Repository;

use PHPUnit\Framework\TestCase;

final class DoctrineAddressRepositorySolidInterfaceTest extends TestCase
{
    public function testLegacyCompositeRepositoryInterfaceRemainsRetired(): void
    {
        self::assertFalse(interface_exists('App\\Addressing\\RepositoryInterface\\AddressRepositoryInterface'));
    }
}
