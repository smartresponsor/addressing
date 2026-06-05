<?php

declare(strict_types=1);

namespace Tests\Repository;

use PHPUnit\Framework\TestCase;

final class DoctrineRepositoryNoLegacyDependencyTest extends TestCase
{
    public function testDoctrineRepositoriesDoNotDependOnLegacyRepositoryClass(): void
    {
        foreach (glob(__DIR__.'/../../src/Repository/Persistence/DoctrineAddress*Repository.php') ?: [] as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringNotContainsString('AddressRepository $legacyRepository', $source, $path);
            self::assertStringNotContainsString('legacyRepository', $source, $path);
        }
    }
}
