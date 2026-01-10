<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Projection\AddressIndex\{IndexProjector, PdoRepository};
use App\Service\Normalize\Normalizer;

/**
 *
 */
final class IndexProjectorTest extends TestCase
{
    /**
     * @return void
     */
    public function testProjectionIntoRepository(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = file_get_contents(__DIR__ . '/../../src/Projection/AddressIndex/schema.sqlite.sql');
        $pdo->exec($sql);
        $repo = new PdoRepository($pdo);
        $projector = new IndexProjector($repo, new Normalizer(), null, false);

        $evt = new AddressCreated('123 Main St', null, 'Houston', 'TX', '77002', 'US');
        $projector->onAddressCreated($evt);

        $list = $repo->search('Hou', 'US', 10);
        IndexProjectorTest::assertGreaterThanOrEqual(1, count($list));
        IndexProjectorTest::assertSame('US', $list[0]->country);
    }
}
