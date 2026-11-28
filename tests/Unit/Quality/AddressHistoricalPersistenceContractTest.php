<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Quality;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\Factory\AddressEntityMapper;
use App\Addressing\Message\AddressOutboxEventMessage;
use App\Addressing\Repository\AddressDoctrineReadRepository;
use App\Addressing\Repository\AddressDoctrineWriteRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressHistoricalPersistenceContractTest extends TestCase
{
    public function testHistoricalCreatedEventNormalizesCountryAndPreservesIdentity(): void
    {
        $payload = AddressOutboxEventMessage::decoratePersistedPayload('AddressCreated', [
            'id' => 'address-1',
            'ownerId' => 'owner-1',
            'vendorId' => null,
            'countryCode' => 'us',
            'createdAt' => '2025-01-01T00:00:00Z',
        ]);

        self::assertSame('US', $payload['countryCode']);
        self::assertSame('2025-01-01T00:00:00+00:00', $payload['createdAt']);
        self::assertSame('address-1', $payload['id']);
        $legacy = AddressOutboxEventMessage::decoratePersistedPayload('AddressCreated', [
            'id' => 'address-1',
            'countryCode' => 'US',
            'createdAt' => '2025-01-01 00:00:00+00:00',
        ]);
        self::assertSame('2025-01-01T00:00:00+00:00', $legacy['createdAt']);
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPersistedEventProvider')]
    public function testMalformedPersistedEventIsRejected(string $event, array $payload): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AddressOutboxEventMessage::decoratePersistedPayload($event, $payload);
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function invalidPersistedEventProvider(): iterable
    {
        $created = ['id' => 'address-1', 'countryCode' => 'US', 'createdAt' => '2025-01-01T00:00:00+00:00'];
        yield 'missing id' => ['AddressCreated', array_diff_key($created, ['id' => true])];
        yield 'blank id' => ['AddressCreated', array_replace($created, ['id' => ' '])];
        yield 'invalid country' => ['AddressCreated', array_replace($created, ['countryCode' => '1A'])];
        yield 'invalid date' => ['AddressCreated', array_replace($created, ['createdAt' => 'tomorrow'])];
        yield 'invalid calendar date' => ['AddressCreated', array_replace($created, ['createdAt' => '2025-02-30T00:00:00+00:00'])];
        yield 'blank owner' => ['AddressCreated', array_replace($created, ['ownerId' => ' '])];
        yield 'missing update timestamp' => ['AddressUpdated', ['id' => 'address-1']];
        yield 'blank provider' => ['AddressValidatedApplied', ['id' => 'address-1', 'validatedAt' => '2025-01-01T00:00:00+00:00', 'provider' => ' ']];
    }

    public function testHistoricalUnicodeNormalizationAndCrudOutboxContract(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([
            AddressEntity::class,
            AddressEvidenceSnapshotEntity::class,
            AddressOutboxEntity::class,
        ]);
        $mapper = new AddressEntityMapper();
        $write = new AddressDoctrineWriteRepository($entityManager, $mapper);
        $read = new AddressDoctrineReadRepository($entityManager, $mapper);
        $source = (new AddressEntity())
            ->setId('historical-address')
            ->setOwnerId('owner-1')
            ->setLine1('Ångströmvägen 1')
            ->setLine2('Апт. 12')
            ->setCity('München')
            ->setRegion('Québec')
            ->setPostalCode('H2Y 1C6')
            ->setCountryCode('CA')
            ->setLine1Norm('ÅNGSTRÖMVÄGEN 1')
            ->setCityNorm('MÜNCHEN')
            ->setRegionNorm('QUÉBEC')
            ->setPostalCodeNorm('H2Y 1C6')
            ->setValidationStatus('pending')
            ->setGovernanceStatus('canonical')
            ->setCreatedAt(new \DateTimeImmutable('2025-01-01T00:00:00+00:00'));

        $write->create($mapper->fromDoctrine($source));
        $entityManager->clear();
        $record = $read->get('historical-address', 'owner-1', null);
        self::assertNotNull($record);
        self::assertSame('Ångströmvägen 1', $record->line1());
        self::assertSame('Апт. 12', $record->line2());
        self::assertSame('München', $record->city());
        self::assertSame('Québec', $record->region());
        self::assertSame('H2Y 1C6', $record->postalCode());
        self::assertSame('CA', $record->countryCode());
        self::assertSame('ÅNGSTRÖMVÄGEN 1', $record->line1Norm());
        self::assertSame('MÜNCHEN', $record->cityNorm());
        self::assertSame('QUÉBEC', $record->regionNorm());
        self::assertSame('H2Y 1C6', $record->postalCodeNorm());

        $source->setLine1('456 Elm St')
            ->setUpdatedAt(new \DateTimeImmutable('2025-02-02T00:00:00+00:00'));
        $write->update($mapper->fromDoctrine($source));
        $entityManager->clear();
        $updated = $read->get('historical-address', 'owner-1', null);
        self::assertNotNull($updated);
        self::assertSame('456 Elm St', $updated->line1());
        self::assertSame('2025-02-02T00:00:00+00:00', $updated->updatedAt());

        $write->delete('historical-address', 'owner-1', null);
        $entityManager->clear();
        self::assertNull($read->get('historical-address', 'owner-1', null));

        $rows = $entityManager->getConnection()->fetchAllAssociative(
            'SELECT event_name, event_version, payload FROM address_outbox ORDER BY id ASC',
        );
        self::assertCount(3, $rows);
        foreach ([
            ['AddressCreated', ['ownerId', 'vendorId', 'countryCode', 'createdAt']],
            ['AddressUpdated', ['updatedAt']],
            ['AddressDeleted', ['deletedAt']],
        ] as $index => [$name, $keys]) {
            self::assertSame($name, $rows[$index]['event_name']);
            self::assertSame(1, (int) $rows[$index]['event_version']);
            $payload = json_decode((string) $rows[$index]['payload'], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertSame('historical-address', $payload['id'] ?? null);
            self::assertSame($name, $payload['eventName'] ?? null);
            self::assertSame('address-outbox.v1', $payload['schemaVersion'] ?? null);
            foreach ($keys as $key) {
                self::assertArrayHasKey($key, $payload);
            }
        }
    }
}
