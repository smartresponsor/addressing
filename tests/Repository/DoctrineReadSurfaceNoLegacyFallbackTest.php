<?php

declare(strict_types=1);

namespace Tests\Repository;

use PHPUnit\Framework\TestCase;

final class DoctrineReadSurfaceNoLegacyFallbackTest extends TestCase
{
    public function testDoctrineRepositoryImplementationsDoNotUseLegacyFallbacks(): void
    {
        foreach (glob(__DIR__.'/../../src/Repository/Persistence/DoctrineAddress*Repository.php') ?: [] as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringNotContainsString('legacyRepository->', $source, $path);
            self::assertStringNotContainsString('legacyRepository', $source, $path);
        }
    }
}
