<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace Tests\Service;

use App\Entity\Address\AddressData;
use App\Repository\Address\AddressRepository;
use App\Service\Address\AddressService;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class AddressServiceTest extends TestCase
{
    private PDO $pdo;
    private AddressRepository $repo;
    private AddressService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec($this->schemaSql());
        $this->repo = new AddressRepository($this->pdo);
        $this->service = new AddressService($this->repo);
    }

    public function testCreateStoresAddress(): void
    {
        $address = $this->makeAddress('addr-1');
        $this->service->create($address);

        $found = $this->repo->get('addr-1');
        static::assertNotNull($found);
        static::assertSame('123 Main St', $found->line1());
    }

    public function testUpdateChangesAddress(): void
    {
        $address = $this->makeAddress('addr-2');
        $this->service->create($address);

        $updated = $this->makeAddress('addr-2', line1: '456 Broad St', updatedAt: (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP'));
        $this->service->update($updated);

        $found = $this->repo->get('addr-2');
        static::assertNotNull($found);
        static::assertSame('456 Broad St', $found->line1());
    }

    public function testCreateStoresContactFields(): void
    {
        $address = $this->makeAddress(
            'addr-2b',
            tag: 'shipping',
            name: 'Jane Doe',
            company: 'Acme Co',
            phone: '+1-555-1234',
            email: 'jane@example.test',
            raw: ['source' => 'import', 'note' => 'primary']
        );
        $this->service->create($address);

        $found = $this->repo->get('addr-2b');
        static::assertNotNull($found);
        static::assertSame('shipping', $found->tag());
        static::assertSame('Jane Doe', $found->name());
        static::assertSame('Acme Co', $found->company());
        static::assertSame('+1-555-1234', $found->phone());
        static::assertSame('jane@example.test', $found->email());
        static::assertSame(['source' => 'import', 'note' => 'primary'], $found->raw());
    }

    public function testSearchReturnsMatchingRows(): void
    {
        $this->service->create($this->makeAddress('addr-3'));
        $this->service->create($this->makeAddress('addr-4', line1: '500 Elm St'));

        $result = $this->service->search(null, null, null, 'Main', 10, null);
        static::assertCount(1, $result['items']);
        static::assertSame('123 Main St', $result['items'][0]->line1());
    }

    public function testDedupeFindsExistingAddress(): void
    {
        $this->service->create($this->makeAddress('addr-5', dedupeKey: 'dedupe-1'));

        $found = $this->service->dedupe('dedupe-1');
        static::assertNotNull($found);
        static::assertSame('addr-5', $found->id());
    }

    public function testOutboxEventRecordedOnCreate(): void
    {
        $this->service->create($this->makeAddress('addr-6'));

        $row = $this->pdo->query('SELECT event_name, event_version, payload FROM address_outbox ORDER BY id ASC')
            ->fetch(PDO::FETCH_ASSOC);

        static::assertNotFalse($row);
        static::assertSame('AddressCreated', $row['event_name']);
        static::assertSame(1, (int)$row['event_version']);
        $payload = json_decode((string)$row['payload'], true);
        static::assertSame('addr-6', $payload['id'] ?? null);
    }

    private function makeAddress(
        string  $id,
        string  $line1 = '123 Main St',
        ?string $tag = null,
        ?string $name = null,
        ?string $company = null,
        ?string $phone = null,
        ?string $email = null,
        ?array  $raw = null,
        ?string $dedupeKey = null,
        ?string $updatedAt = null
    ): AddressData
    {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        return new AddressData(
            $id,
            'owner-1',
            'vendor-1',
            $tag,
            $name,
            $company,
            $phone,
            $email,
            $raw,
            $line1,
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'pending',
            null,
            null,
            $dedupeKey,
            $now,
            $updatedAt,
            null
        );
    }

    private function schemaSql(): string
    {
        return <<<'SQL'
CREATE TABLE address_entity (
  id TEXT PRIMARY KEY,
  owner_id TEXT NULL,
  vendor_id TEXT NULL,
  tag TEXT NULL,
  name TEXT NULL,
  company TEXT NULL,
  phone TEXT NULL,
  email TEXT NULL,
  raw TEXT NULL,
  line1 TEXT NOT NULL,
  line2 TEXT NULL,
  city TEXT NOT NULL,
  region TEXT NULL,
  postal_code TEXT NULL,
  country_code TEXT NOT NULL,
  line1_norm TEXT NULL,
  city_norm TEXT NULL,
  region_norm TEXT NULL,
  postal_code_norm TEXT NULL,
  latitude REAL NULL,
  longitude REAL NULL,
  geohash TEXT NULL,
  validation_status TEXT NOT NULL,
  validation_provider TEXT NULL,
  validated_at TEXT NULL,
  dedupe_key TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NULL,
  deleted_at TEXT NULL,
  validation_fingerprint TEXT NULL,
  validation_raw TEXT NULL,
  validation_verdict TEXT NULL,
  validation_deliverable INTEGER NULL,
  validation_granularity TEXT NULL,
  validation_quality INTEGER NULL
);

CREATE TABLE address_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stream TEXT NOT NULL DEFAULT 'address',
  event_name TEXT NOT NULL,
  event_version INTEGER NOT NULL,
  payload TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at TEXT NULL,
  published_attempt INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NULL
);
SQL;
    }
}
