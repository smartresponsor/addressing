<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use PHPUnit\Framework\TestCase;

final class DoctrineBootstrapSurfaceTest extends TestCase
{
    public function testLegacyBootstrapSchemaHelpersAreRemoved(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileDoesNotExist($root.'/src/Integration/Persistence/AddressPdoFactory.php');
        self::assertFileDoesNotExist($root.'/src/Integration/Persistence/AddressSchemaManager.php');
        self::assertFileExists($root.'/src/Doctrine/AddressDoctrineSchemaManager.php');
        self::assertFileExists($root.'/src/Entity/AddressOutboxEntity.php');
    }
}
