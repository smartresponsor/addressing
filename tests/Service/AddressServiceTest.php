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
        static::assertSame('manual', $found->sourceType());
        static::assertSame('unit-test', $found->sourceSystem());
        static::assertSame('sha256:addr-1', $found->providerDigest());
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

    public function testSearchSupportsOperationalFilters(): void
    {
        $this->service->create($this->makeAddress('addr-filter-1'));
        $withGovernance = new AddressData(
            'addr-filter-2',
            'owner-1',
            'vendor-1',
            '500 Elm St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            '500elmst',
            'houston',
            'tx',
            '77002',
            null,
            null,
            null,
            'validated',
            'validator-suite',
            '2025-01-03 00:00:00+00:00',
            '500elmst|houston|tx|77002|US|owner-1|vendor-1',
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            null,
            null,
            'fingerprint-filter',
            ['provider' => 'validator-suite'],
            ['quality' => 93],
            true,
            'premise',
            93,
            'validator-suite',
            'validator',
            'run-filter',
            'canon-w11',
            ['line1' => '500 Elm St'],
            ['line1Norm' => '500elmst'],
            'digest-filter',
            'duplicate',
            'addr-master',
            null,
            null,
            null,
            '2025-01-15 00:00:00+00:00',
            'monthly',
            'validator-suite',
            'validated',
            93
        );
        $this->service->create($withGovernance);

        $result = $this->service->search('owner-1', 'vendor-1', null, null, 10, null, [
            'sourceType' => 'validator',
            'governanceStatus' => 'duplicate',
            'hasEvidence' => true,
            'revalidationDueBefore' => '2025-01-31 00:00:00+00:00',
        ]);

        static::assertCount(1, $result['items']);
        static::assertSame('addr-filter-2', $result['items'][0]->id());
        static::assertSame('duplicate', $result['items'][0]->governanceStatus());
        static::assertSame('monthly', $result['items'][0]->revalidationPolicy());
    }

    public function testPatchOperationalUpdatesGovernanceAndRevalidation(): void
    {
        $this->service->create($this->makeAddress('addr-patch-1'));

        $ok = $this->service->patchOperational('addr-patch-1', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'superseded',
            'supersededById' => 'addr-master-1',
            'revalidationDueAt' => '2025-05-01 00:00:00+00:00',
            'revalidationPolicy' => 'quarterly',
            'lastValidationProvider' => 'validator-suite',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => 97,
        ]);

        self::assertTrue($ok);
        $saved = $this->service->get('addr-patch-1', 'owner-1', 'vendor-1');
        self::assertNotNull($saved);
        self::assertSame('superseded', $saved->governanceStatus());
        self::assertSame('addr-master-1', $saved->supersededById());
        self::assertSame('2025-05-01 00:00:00+00:00', $saved->revalidationDueAt());
        self::assertSame('quarterly', $saved->revalidationPolicy());
        self::assertSame('validator-suite', $saved->lastValidationProvider());
        self::assertSame('validated', $saved->lastValidationStatus());
        self::assertSame(97, $saved->lastValidationScore());

        $row = $this->pdo->query('SELECT event_name, payload FROM address_outbox ORDER BY id DESC LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('AddressOperationalPatched', $row['event_name']);
        $payload = json_decode((string) $row['payload'], true);
        self::assertSame('superseded', $payload['governanceStatus'] ?? null);
        self::assertSame('addr-master-1', $payload['governanceLinkId'] ?? null);
        self::assertSame('quarterly', $payload['revalidationPolicy'] ?? null);
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

    public function testRevalidationFieldsPersistOnCreateAndUpdate(): void
    {
        $address = $this->makeAddress('addr-reval');
        $this->service->create($address);

        $updated = new AddressData(
            'addr-reval',
            'owner-1',
            'vendor-1',
            '123 Main St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            '123mainst',
            'houston',
            'tx',
            '77002',
            null,
            null,
            null,
            'validated',
            'validator-suite',
            '2025-01-02 00:00:00+00:00',
            '123mainst|houston|tx|77002|US|owner-1|vendor-1',
            $address->createdAt(),
            '2025-01-03 00:00:00+00:00',
            null,
            'fingerprint-reval',
            ['provider' => 'validator-suite'],
            ['quality' => 91],
            true,
            'premise',
            91,
            'validator-suite',
            'validator',
            'run-reval',
            'canon-w08',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123mainst'],
            'digest-reval',
            'canonical',
            null,
            null,
            null,
            null,
            '2025-04-01 00:00:00+00:00',
            'monthly',
            'validator-suite',
            'validated',
            91
        );

        $this->service->update($updated);

        $saved = $this->service->get('addr-reval', 'owner-1', 'vendor-1');
        self::assertNotNull($saved);
        self::assertSame('2025-04-01 00:00:00+00:00', $saved->revalidationDueAt());
        self::assertSame('monthly', $saved->revalidationPolicy());
        self::assertSame('validator-suite', $saved->lastValidationProvider());
        self::assertSame('validated', $saved->lastValidationStatus());
        self::assertSame(91, $saved->lastValidationScore());
    }

    public function testGovernanceLinksPersistThroughCreateAndUpdate(): void
    {
        $address = $this->makeAddress('addr-gov');
        $this->service->create($address);

        $duplicate = new AddressData(
            'addr-gov',
            'owner-1',
            'vendor-1',
            '123 Main St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            '123mainst',
            'houston',
            'tx',
            '77002',
            null,
            null,
            null,
            'validated',
            'unit',
            '2025-01-01 00:00:00+00:00',
            '123mainst|houston|tx|77002|US|owner-1|vendor-1',
            $address->createdAt(),
            '2025-01-02 00:00:00+00:00',
            null,
            'fingerprint-gov',
            ['provider' => 'unit'],
            ['quality' => 95],
            true,
            'premise',
            95,
            'validator-suite',
            'validator',
            'run-gov',
            'canon-w08',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123mainst'],
            'digest-gov',
            'duplicate',
            'addr-master',
            null,
            null,
            null,
            '2025-06-01 00:00:00+00:00',
            'quarterly',
            'unit',
            'validated',
            95
        );

        $this->service->update($duplicate);

        $saved = $this->service->get('addr-gov', 'owner-1', 'vendor-1');
        self::assertNotNull($saved);
        self::assertSame('duplicate', $saved->governanceStatus());
        self::assertSame('addr-master', $saved->duplicateOfId());
        self::assertNull($saved->supersededById());
        self::assertNull($saved->aliasOfId());
        self::assertNull($saved->conflictWithId());
        self::assertSame('2025-06-01 00:00:00+00:00', $saved->revalidationDueAt());
        self::assertSame('quarterly', $saved->revalidationPolicy());
        self::assertSame('unit', $saved->lastValidationProvider());
        self::assertSame('validated', $saved->lastValidationStatus());
        self::assertSame(95, $saved->lastValidationScore());
    }

    public function testInvalidLifecycleAndGovernanceTokensAreSanitizedOnPersist(): void
    {
        $address = new AddressData(
            'addr-sanitize',
            'owner-1',
            'vendor-1',
            '123 Main St',
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
            'mystery-status',
            null,
            null,
            null,
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'unit-test',
            'odd-source',
            'fixture:addr-sanitize',
            'canon-w11',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123mainst'],
            'sha256:addr-sanitize',
            'wild-status',
            null,
            null,
            null,
            null,
            '2025-07-01 00:00:00+00:00',
            'whenever',
            'validator-suite',
            'unclear',
            77
        );

        $this->service->create($address);

        $saved = $this->service->get('addr-sanitize', 'owner-1', 'vendor-1');
        self::assertNotNull($saved);
        self::assertSame('unknown', $saved->validationStatus());
        self::assertNull($saved->sourceType());
        self::assertSame('canonical', $saved->governanceStatus());
        self::assertNull($saved->revalidationPolicy());
        self::assertNull($saved->lastValidationStatus());
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
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'unit-test',
            'manual',
            'fixture:'.$id,
            'canon-w08',
            ['line1' => $line1, 'city' => 'Houston', 'region' => 'TX', 'postalCode' => '77002', 'countryCode' => 'US'],
            ['line1Norm' => '123mainst', 'cityNorm' => 'houston', 'regionNorm' => 'tx', 'postalCodeNorm' => '77002'],
            'sha256:'.$id,
            'canonical',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
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
    CHECK (validation_status IN ('unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden')),
  validation_provider TEXT NULL,
  validated_at TEXT NULL,
  source_system TEXT NULL,
  source_type TEXT NULL
    CHECK (source_type IS NULL OR source_type IN ('manual', 'import', 'partner', 'validator', 'override', 'migration')),
  source_reference TEXT NULL,
  normalization_version TEXT NULL,
  raw_input_snapshot TEXT NULL,
  normalized_snapshot TEXT NULL,
  provider_digest TEXT NULL,
  governance_status TEXT NOT NULL DEFAULT 'canonical'
    CHECK (governance_status IN ('canonical', 'duplicate', 'superseded', 'alias', 'conflict')),
  duplicate_of_id TEXT NULL,
  superseded_by_id TEXT NULL,
  alias_of_id TEXT NULL,
  conflict_with_id TEXT NULL,
  revalidation_due_at TEXT NULL,
  revalidation_policy TEXT NULL
    CHECK (revalidation_policy IS NULL OR revalidation_policy IN ('manual', 'on-change', 'daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual')),
  last_validation_provider TEXT NULL,
  last_validation_status TEXT NULL
    CHECK (last_validation_status IS NULL OR last_validation_status IN ('normalized', 'validated', 'rejected', 'uncertain', 'overridden')),
  last_validation_score INTEGER NULL,
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
