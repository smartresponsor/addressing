<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Projection\AddressIndex\{IndexRecord,PdoRepository};

/**
 *
 */

/**
 *
 */
final class AddressIndexRepositoryTest extends TestCase
{
    private PdoRepository $repo;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sql = file_get_contents(__DIR__ . '/../../src/Projection/AddressIndex/schema.sqlite.sql');
        $pdo->exec($sql);
        $this->repo = new PdoRepository($pdo);
    }

    /**
     * @return void
     */
    public function testUpsertAndFetch(): void
    {
        $r = new IndexRecord(
            digest: str_repeat('a', 64),
            line1: '123 Main St',
            line2: null,
            city: 'Houston',
            region: 'TX',
            postal: '77002',
            country: 'US',
            lat: 29.7604,
            lon: -95.3698,
            display: '123 Main St, Houston, TX 77002, USA',
            provider: 'test',
            confidence: 0.9,
            geoKey: IndexRecord::geokey(29.7604, -95.3698),
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00',
        );
        $this->repo->upsert($r);
        $got = $this->repo->getByDigest($r->digest);
        static::assertNotNull($got);
        static::assertSame('US', $got->country);
        static::assertSame('Houston', $got->city);
        $list = $this->repo->search('Hou', 'US', 10);
        static::assertGreaterThanOrEqual(1, count($list));
    }
}
