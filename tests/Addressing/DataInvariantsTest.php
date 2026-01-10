<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */

declare(strict_types=1);

namespace Tests\Addressing;

use PDO;

/**
 *
 */

/**
 *
 */
final class DataInvariantsTest extends AddressRepositoryTestCase
{
    /**
     * @return void
     */
    public function testCrudConsistencyAndOutboxPayloadShape(): void
    {
        $address = $this->makeAddress();
        $this->repo->create($address);

        $fetched = $this->repo->get($address->id());
        static::assertNotNull($fetched);
        static::assertSame($address->line1(), $fetched->line1());
        static::assertSame($address->city(), $fetched->city());
        static::assertSame($address->countryCode(), $fetched->countryCode());

        $updated = $this->makeAddress([
            'line1' => '456 Elm St',
            'updatedAt' => '2025-02-02T00:00:00Z',
        ]);
        $this->repo->update($updated);

        $afterUpdate = $this->repo->get($address->id());
        static::assertNotNull($afterUpdate);
        static::assertSame('456 Elm St', $afterUpdate->line1());
        static::assertSame('2025-02-02T00:00:00Z', $afterUpdate->updatedAt());

        $this->repo->delete($address->id());
        static::assertNull($this->repo->get($address->id()));

        $rows = $this->pdo->query('SELECT event_name, payload FROM address_outbox ORDER BY id ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
        static::assertCount(3, $rows);

        $createdPayload = json_decode($rows[0]['payload'], true);
        static::assertSame('AddressCreated', $rows[0]['event_name']);
        static::assertIsArray($createdPayload);
        static::assertSame($address->id(), $createdPayload['id']);
        static::assertArrayHasKey('ownerId', $createdPayload);
        static::assertArrayHasKey('vendorId', $createdPayload);
        static::assertArrayHasKey('countryCode', $createdPayload);
        static::assertArrayHasKey('createdAt', $createdPayload);

        $updatedPayload = json_decode($rows[1]['payload'], true);
        static::assertSame('AddressUpdated', $rows[1]['event_name']);
        static::assertIsArray($updatedPayload);
        static::assertSame($address->id(), $updatedPayload['id']);
        static::assertArrayHasKey('updatedAt', $updatedPayload);

        $deletedPayload = json_decode($rows[2]['payload'], true);
        static::assertSame('AddressDeleted', $rows[2]['event_name']);
        static::assertIsArray($deletedPayload);
        static::assertSame($address->id(), $deletedPayload['id']);
        static::assertArrayHasKey('deletedAt', $deletedPayload);
    }
}
