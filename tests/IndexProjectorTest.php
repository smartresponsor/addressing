<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Projection\AddressIndex\IndexProjector;
use App\Projection\AddressIndex\Normalizer;
use App\Projection\AddressIndex\PdoRepository;
use App\Service\Application\Event\AddressCreatedEvent;
use PHPUnit\Framework\TestCase;

final class IndexProjectorTest extends TestCase
{
    public function testProjectionIntoRepository(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = file_get_contents(__DIR__.'/../src/Projection/AddressIndex/schema.sqlite.sql');
        $pdo->exec($sql);
        $repo = new PdoRepository($pdo);
        $projector = new IndexProjector($repo, new Normalizer(), null, false);

        $evt = new AddressCreatedEvent('123 Main St', null, 'Houston', 'TX', '77002', 'US');
        $projector->onAddressCreated($evt);

        $list = $repo->search('Hou', 'US', 10);
        self::assertGreaterThanOrEqual(1, count($list));
        self::assertSame('US', $list[0]->country);
    }
}
