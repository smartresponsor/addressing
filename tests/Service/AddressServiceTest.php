<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace Tests\Service;

use App\Entity\Record\Address\AddressData;
use App\Repository\Persistence\Address\AddressRepository;
use App\Service\Application\Address\AddressService;
use PHPUnit\Framework\TestCase;

final class AddressServiceTest extends TestCase
{
    private \PDO $pdo;
    private AddressRepository $repo;
    private AddressService $service;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec($this->schemaSql());
        $this->repo = new AddressRepository($this->pdo);
        $this->service = new AddressService($this->repo);
    }

    public function testCreateStoresAddress(): void
    {
        $address = $this->makeAddress('addr-1');
        $this->service->create($address);

        $found = $this->repo->get('addr-1', $address->ownerId(), $address->vendorId());
        static::assertNotNull($found);
        static::assertSame('123 Main St', $found->line1());
    }

    public function testUpdateChangesAddress(): void
    {
        $address = $this->makeAddress('addr-2');
        $this->service->create($address);

        $updated = $this->makeAddress('addr-2', line1: '456 Broad St', updatedAt: (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'));
        $this->service->update($updated);

        $found = $this->repo->get('addr-2', $updated->ownerId(), $updated->vendorId());
        static::assertNotNull($found);
        static::assertSame('456 Broad St', $found->line1());
    }

    public function testSearchReturnsMatchingRows(): void
    {
        $this->service->create($this->makeAddress('addr-3'));
        $this->service->create($this->makeAddress('addr-4', line1: '500 Elm St'));

        $result = $this->service->search('owner-1', 'vendor-1', null, 'Main', 10, null);
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
            ->fetch(\PDO::FETCH_ASSOC);

        static::assertNotFalse($row);
        static::assertSame('AddressCreated', $row['event_name']);
        static::assertSame(1, (int) $row['event_version']);
        $payload = json_decode((string) $row['payload'], true);
        static::assertSame('addr-6', $payload['id'] ?? null);
    }

    public function testUpdateMissingRowDoesNotWriteOutbox(): void
    {
        $this->service->update($this->makeAddress('missing-1'));

        static::assertSame(0, $this->outboxCount());
    }

    public function testDeleteMissingRowDoesNotWriteOutbox(): void
    {
        $this->repo->delete('missing-2', 'owner-1', 'vendor-1');

        static::assertSame(0, $this->outboxCount());
    }

    public function testTenantIsolationForGetUpdateAndSearch(): void
    {
        $tenantOne = $this->makeAddress('addr-7', ownerId: 'owner-1', vendorId: 'vendor-1');
        $tenantTwo = $this->makeAddress('addr-8', ownerId: 'owner-2', vendorId: 'vendor-2', line1: '987 Other St');
        $this->service->create($tenantOne);
        $this->service->create($tenantTwo);

        $foundOtherTenant = $this->service->get('addr-7', 'owner-2', 'vendor-2');
        static::assertNull($foundOtherTenant);

        $updateWrongTenant = $this->makeAddress('addr-7', ownerId: 'owner-2', vendorId: 'vendor-2', line1: 'Hacked St');
        $this->service->update($updateWrongTenant);

        $stillOriginal = $this->service->get('addr-7', 'owner-1', 'vendor-1');
        static::assertNotNull($stillOriginal);
        static::assertSame('123 Main St', $stillOriginal->line1());

        $results = $this->service->search('owner-2', 'vendor-2', null, null, 10, null);
        static::assertCount(1, $results['items']);
        static::assertSame('addr-8', $results['items'][0]->id());
    }

    private function outboxCount(): int
    {
        $count = $this->pdo->query('SELECT COUNT(*) FROM address_outbox')->fetchColumn();

        return (int) $count;
    }

    private function makeAddress(
        string $id,
        string $ownerId = 'owner-1',
        string $vendorId = 'vendor-1',
        string $line1 = '123 Main St',
        ?string $dedupeKey = null,
        ?string $updatedAt = null,
    ): AddressData {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        return new AddressData(
            $id,
            $ownerId,
            $vendorId,
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
            'unknown',
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
  line1 TEXT NOT NULL,
  line2 TEXT NULL,
  city TEXT NOT NULL,
  region TEXT NULL,
  postal_code TEXT NULL,
  country_code TEXT NOT NULL CHECK (length(country_code) = 2),
  line1_norm TEXT NULL,
  city_norm TEXT NULL,
  region_norm TEXT NULL,
  postal_code_norm TEXT NULL,
  latitude REAL NULL,
  longitude REAL NULL,
  geohash TEXT NULL,
  validation_status TEXT NOT NULL DEFAULT 'unknown'
    CHECK (validation_status IN ('unknown', 'normalized', 'validated')),
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
  ,CONSTRAINT address_tenant_scope_chk CHECK (owner_id IS NOT NULL OR vendor_id IS NOT NULL)
);

CREATE UNIQUE INDEX address_dedupe_unique
  ON address_entity (dedupe_key) WHERE dedupe_key IS NOT NULL;

CREATE TRIGGER trg_address_touch_updated_at
  AFTER UPDATE ON address_entity
  FOR EACH ROW
  WHEN NEW.updated_at IS OLD.updated_at
BEGIN
  UPDATE address_entity
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.id;
END;

CREATE TRIGGER trg_address_dedupe_autofill
  AFTER INSERT ON address_entity
  FOR EACH ROW
  WHEN NEW.dedupe_key IS NULL
BEGIN
  UPDATE address_entity
    SET dedupe_key = CASE
      WHEN coalesce(NEW.line1_norm, '') = ''
        AND coalesce(NEW.city_norm, '') = ''
        AND coalesce(NEW.region_norm, '') = ''
        AND coalesce(NEW.postal_code_norm, '') = ''
        AND coalesce(NEW.country_code, '') = '' THEN NULL
      ELSE lower(replace(coalesce(NEW.line1_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.city_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.region_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.postal_code_norm, ''), ' ', '')) || '|' ||
        upper(coalesce(NEW.country_code, ''))
      END
    WHERE id = NEW.id AND NEW.dedupe_key IS NULL;
END;

CREATE TRIGGER trg_address_dedupe_autofill_update
  AFTER UPDATE ON address_entity
  FOR EACH ROW
  WHEN NEW.dedupe_key IS NULL
BEGIN
  UPDATE address_entity
    SET dedupe_key = CASE
      WHEN coalesce(NEW.line1_norm, '') = ''
        AND coalesce(NEW.city_norm, '') = ''
        AND coalesce(NEW.region_norm, '') = ''
        AND coalesce(NEW.postal_code_norm, '') = ''
        AND coalesce(NEW.country_code, '') = '' THEN NULL
      ELSE lower(replace(coalesce(NEW.line1_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.city_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.region_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.postal_code_norm, ''), ' ', '')) || '|' ||
        upper(coalesce(NEW.country_code, ''))
      END
    WHERE id = NEW.id AND NEW.dedupe_key IS NULL;
END;

CREATE TABLE address_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stream TEXT NOT NULL DEFAULT 'address',
  event_name TEXT NOT NULL,
  event_version INTEGER NOT NULL,
  payload TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at TEXT NULL,
  locked_at TEXT NULL,
  locked_by TEXT NULL,
  published_attempt INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NULL
);
SQL;
    }
}
