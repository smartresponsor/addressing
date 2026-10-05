<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Addressing\Entity\AddressIndexEntity;
use App\Addressing\Event\AddressCreatedEvent;
use App\Addressing\Event\AddressUpdatedEvent;
use App\Addressing\Normalizer\AddressIndexNormalizer;
use App\Addressing\Projection\AddressIndex\AddressIndexProjector;
use App\Addressing\Projection\AddressIndex\AddressIndexRecord;
use App\Addressing\Repository\AddressIndex\AddressDoctrineIndexRepository;
use App\Addressing\Service\Projection\AddressIndex\AddressIndexProjectorService;
use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\Geocode\AddressGeocodeResult;
use App\Addressing\Value\Primitive\AddressRegion;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressIndexProjectorTest extends TestCase
{
    public function testIndexRecordBuildsGeocodedAndNullablePersistencePayloads(): void
    {
        self::assertSame('', AddressIndexRecord::geokey(null, -95.36));
        self::assertSame('', AddressIndexRecord::geokey(29.76, null));
        self::assertSame('+29.76000:-95.36000', AddressIndexRecord::geokey(29.76, -95.36));

        $normalized = [
            'line1' => new AddressStreetLine('123 Main St'),
            'line2' => new AddressStreetLine('Suite 5'),
            'city' => 'Houston',
            'region' => new AddressRegion('tx'),
            'postal' => new AddressPostalCode('77002'),
            'country' => new AddressCountryCode('us'),
            'digest' => 'digest-1',
        ];

        $withoutGeocode = AddressIndexRecord::fromNormalized($normalized);
        self::assertNull($withoutGeocode->lat);
        self::assertNull($withoutGeocode->lon);
        self::assertSame('', $withoutGeocode->geoKey);
        self::assertSame('Suite 5', $withoutGeocode->line2);

        $withGeocode = AddressIndexRecord::fromNormalized(
            $normalized,
            new AddressGeocodeResult(29.76, -95.36, 'Houston, TX', 'unit-provider', 0.97),
        );
        $payload = $withGeocode->toArray();

        self::assertSame('digest-1', $payload['digest']);
        self::assertSame('123 Main St', $payload['line1']);
        self::assertSame('Suite 5', $payload['line2']);
        self::assertSame('Houston', $payload['city']);
        self::assertSame('TX', $payload['region']);
        self::assertSame('77002', $payload['postal']);
        self::assertSame('US', $payload['country']);
        self::assertSame(29.76, $payload['lat']);
        self::assertSame(-95.36, $payload['lon']);
        self::assertSame('Houston, TX', $payload['display']);
        self::assertSame('unit-provider', $payload['provider']);
        self::assertSame(0.97, $payload['confidence']);
        self::assertSame('+29.76000:-95.36000', $payload['geo_key']);
        self::assertSame($payload['created_at'], $payload['updated_at']);
    }

    public function testProjectionIntoRepository(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressIndexEntity::class]);
        $repo = new AddressDoctrineIndexRepository($entityManager);
        $projector = new AddressIndexProjector($repo, new AddressIndexNormalizer(), new AddressIndexProjectorService());

        $evt = new AddressCreatedEvent('123 Main St', null, 'Houston', 'TX', '77002', 'US');
        self::assertSame('address.created', $evt->nameEntity());
        self::assertInstanceOf(\DateTimeImmutable::class, $evt->occurredAt());
        $projector->onAddressCreated($evt);

        $list = $repo->search('Hou', 'US', 10);
        self::assertGreaterThanOrEqual(1, count($list));
        self::assertSame('US', $list[0]->country);
    }

    public function testUpdatedEventProjectsThroughTheSameIndexPipeline(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressIndexEntity::class]);
        $repo = new AddressDoctrineIndexRepository($entityManager);
        $projector = new AddressIndexProjector($repo, new AddressIndexNormalizer(), new AddressIndexProjectorService());

        $event = new AddressUpdatedEvent('500 Test Ave', 'Suite 200', 'Dallas', 'TX', '75201', 'US');
        self::assertSame('address.updated', $event->nameEntity());
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredAt());
        $projector->onAddressUpdated($event);

        $list = $repo->search('Dal', 'US', 10);
        self::assertCount(1, $list);
        self::assertSame('Dallas', $list[0]->city);
        self::assertSame('Suite 200', $list[0]->line2);
    }
}
