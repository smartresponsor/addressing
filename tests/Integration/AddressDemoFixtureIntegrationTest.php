<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Integration;

use App\Fixture\AddressDemoFixtureService;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressDemoFixtureIntegrationTest extends TestCase
{
    public function testFixtureResetsAndLoadsDemoData(): void
    {
        $pdo = TestDatabase::createPdo();
        TestDatabase::resetAddressSchema($pdo);

        $fixture = new AddressDemoFixtureService($pdo);
        $loaded = $fixture->resetAndLoad(5);

        self::assertSame(5, $loaded);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM address_entity')->fetchColumn();
        self::assertSame(5, $count);

        $outboxCount = (int) $pdo->query('SELECT COUNT(*) FROM address_outbox')->fetchColumn();
        self::assertSame(5, $outboxCount);
    }

}
