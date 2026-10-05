<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Projection;

use App\Addressing\Entity\AddressIndexEntity;
use App\Addressing\Projection\AddressIndex\AddressIndexRecord;
use App\Addressing\Repository\AddressIndex\AddressDoctrineIndexRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressIndexRepositoryTest extends TestCase
{
    private AddressDoctrineIndexRepository $repo;

    protected function setUp(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressIndexEntity::class]);
        $this->repo = new AddressDoctrineIndexRepository($entityManager);
    }

    public function testUpsertAndFetch(): void
    {
        $r = new AddressIndexRecord(
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
            geoKey: AddressIndexRecord::geokey(29.7604, -95.3698),
            createdAt: '2024-01-01 00:00:00',
            updatedAt: '2024-01-01 00:00:00',
        );
        $this->repo->upsert($r);
        $got = $this->repo->getByDigest($r->digest);
        self::assertNotNull($got);
        self::assertSame('US', $got->country);
        self::assertSame('Houston', $got->city);
        $list = $this->repo->search('Hou', 'US', 10);
        self::assertGreaterThanOrEqual(1, count($list));
    }

    public function testRecordFactoryCoversGeocodedAndNullableProjectionPaths(): void
    {
        $record = AddressIndexRecord::fromNormalized([
            'line1' => new \App\Addressing\Value\AddressStreetLine('500 Test Ave'),
            'line2' => new \App\Addressing\Value\AddressStreetLine('Suite 200'),
            'city' => 'Dallas',
            'region' => new \App\Addressing\Value\Primitive\AddressRegion('tx'),
            'postal' => new \App\Addressing\Value\AddressPostalCode('75201'),
            'country' => new \App\Addressing\Value\AddressCountryCode('us'),
            'digest' => str_repeat('b', 64),
        ], new \App\Addressing\Value\Geocode\AddressGeocodeResult(
            32.7767,
            -96.797,
            '500 Test Ave, Dallas, TX 75201, USA',
            'test-provider',
            0.95,
        ));

        self::assertSame('+32.77670:-96.79700', $record->geoKey);
        self::assertSame('Suite 200', $record->line2);
        self::assertSame('test-provider', $record->provider);
        self::assertSame('+32.77670:-96.79700', $record->toArray()['geo_key']);

        $withoutGeocode = AddressIndexRecord::fromNormalized([
            'line1' => new \App\Addressing\Value\AddressStreetLine('123 Main St'),
            'line2' => null,
            'city' => 'Houston',
            'region' => new \App\Addressing\Value\Primitive\AddressRegion('tx'),
            'postal' => new \App\Addressing\Value\AddressPostalCode('77002'),
            'country' => new \App\Addressing\Value\AddressCountryCode('us'),
            'digest' => str_repeat('c', 64),
        ]);

        self::assertNull($withoutGeocode->lat);
        self::assertNull($withoutGeocode->lon);
        self::assertSame('', $withoutGeocode->geoKey);
    }

    public function testGeokeyRequiresBothCoordinates(): void
    {
        self::assertSame('', AddressIndexRecord::geokey(null, -95.0));
        self::assertSame('', AddressIndexRecord::geokey(29.0, null));
        self::assertSame('+29.76040:-95.36980', AddressIndexRecord::geokey(29.7604, -95.3698));
    }
}
