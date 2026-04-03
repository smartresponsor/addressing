<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Contract\Message\AddressValidated;
use App\Integration\Persistence\AddressEvidenceSnapshotWriter;
use App\Integration\Persistence\AddressOutboxWriter;
use App\Integration\Persistence\AddressTenantScopeSqlHelper;
use App\Integration\Persistence\AddressValidatedMutationPlanBuilder;
use App\Service\Application\AddressValidatedApplierService;
use App\Service\Application\AddressValidatedPayloadFactory;
use PHPUnit\Framework\TestCase;

final class AddressValidatedApplierTest extends TestCase
{
    private \PDO $pdo;
    private AddressValidatedApplierService $applier;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec($this->schemaSql());
        $payloadFactory = new AddressValidatedPayloadFactory();
        $this->applier = new AddressValidatedApplierService(
            $this->pdo,
            new AddressTenantScopeSqlHelper(),
            $payloadFactory,
            new AddressValidatedMutationPlanBuilder($this->pdo, $payloadFactory),
            new AddressEvidenceSnapshotWriter($this->pdo, $payloadFactory),
            new AddressOutboxWriter($this->pdo),
        );
    }

    public function testApplyWorksOnSqliteWithoutPgsqlLockSyntax(): void
    {
        $this->insertAddress('addr-1', 'owner-1', 'vendor-1');

        $validated = AddressValidated::fromArray([
            'line1Norm' => 'main st',
            'cityNorm' => 'houston',
            'validationProvider' => 'unit',
            'sourceSystem' => 'validator-suite',
            'sourceType' => 'validator',
            'sourceReference' => 'run-1',
            'normalizationVersion' => 'canon-w08',
            'rawInput' => ['line1' => '123 Main St', 'city' => 'Houston'],
            'normalizedSnapshot' => ['line1Norm' => 'main st', 'cityNorm' => 'houston'],
            'providerDigest' => 'digest-1',
            'governanceStatus' => 'superseded',
            'supersededById' => 'addr-2',
            'revalidationDueAt' => '2025-03-01T00:00:00+00:00',
            'revalidationPolicy' => 'quarterly',
            'lastValidationProvider' => 'unit',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => 87,
        ]);

        $this->applier->apply('addr-1', $validated, 'owner-1', 'vendor-1');

        $row = $this->fetchAssocRow("SELECT validation_status, line1_norm, source_system, source_type, provider_digest, governance_status, superseded_by_id, revalidation_due_at, revalidation_policy, last_validation_provider, last_validation_status, last_validation_score FROM address_entity WHERE id = 'addr-1'");
        self::assertSame('validated', $this->stringField($row, 'validation_status'));
        self::assertSame('main st', $this->stringField($row, 'line1_norm'));
        self::assertSame('validator-suite', $this->stringField($row, 'source_system'));
        self::assertSame('validator', $this->stringField($row, 'source_type'));
        self::assertSame('digest-1', $this->stringField($row, 'provider_digest'));
        self::assertSame('superseded', $this->stringField($row, 'governance_status'));
        self::assertSame('addr-2', $this->stringField($row, 'superseded_by_id'));
        self::assertStringStartsWith('2025-03-01', $this->stringField($row, 'revalidation_due_at'));
        self::assertSame('quarterly', $this->stringField($row, 'revalidation_policy'));
        self::assertSame('unit', $this->stringField($row, 'last_validation_provider'));
        self::assertSame('validated', $this->stringField($row, 'last_validation_status'));
        self::assertSame(87, $this->intField($row, 'last_validation_score'));

        $snapshot = $this->fetchAssocRow("SELECT source_system, source_type, source_reference, validated_by, validation_status, validation_score, provider_digest FROM address_evidence_snapshot WHERE address_id = 'addr-1' ORDER BY created_at DESC, id DESC LIMIT 1");
        self::assertSame('validator-suite', $this->stringField($snapshot, 'source_system'));
        self::assertSame('validator', $this->stringField($snapshot, 'source_type'));
        self::assertSame('run-1', $this->stringField($snapshot, 'source_reference'));
        self::assertSame('unit', $this->stringField($snapshot, 'validated_by'));
        self::assertSame('validated', $this->stringField($snapshot, 'validation_status'));
        self::assertSame(87, $this->intField($snapshot, 'validation_score'));
        self::assertSame('digest-1', $this->stringField($snapshot, 'provider_digest'));

        $outbox = $this->fetchAssocRow('SELECT event_name, event_version, payload FROM address_outbox ORDER BY id DESC LIMIT 1');
        self::assertSame('AddressValidatedApplied', $this->stringField($outbox, 'event_name'));
        self::assertSame(1, $this->intField($outbox, 'event_version'));
        $payload = $this->decodeJsonObject($this->stringField($outbox, 'payload'));
        self::assertSame('AddressValidatedApplied', $payload['eventName'] ?? null);
        self::assertSame('address-outbox.v1', $payload['schemaVersion'] ?? null);
        self::assertSame(1, $payload['eventVersion'] ?? null);
    }

    public function testApplyRejectsWrongTenantScope(): void
    {
        $this->insertAddress('addr-2', 'owner-A', 'vendor-A');

        $validated = AddressValidated::fromArray([
            'line1Norm' => 'changed',
            'validationProvider' => 'unit',
            'sourceSystem' => 'validator-suite',
            'sourceType' => 'validator',
            'sourceReference' => 'run-1',
            'normalizationVersion' => 'canon-w08',
            'rawInput' => ['line1' => '123 Main St', 'city' => 'Houston'],
            'normalizedSnapshot' => ['line1Norm' => 'main st', 'cityNorm' => 'houston'],
            'providerDigest' => 'digest-1',
            'governanceStatus' => 'superseded',
            'supersededById' => 'addr-2',
            'revalidationDueAt' => '2025-03-01T00:00:00+00:00',
            'revalidationPolicy' => 'quarterly',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not_found');
        $this->applier->apply('addr-2', $validated, 'owner-B', 'vendor-B');
    }

    private function insertAddress(string $id, string $ownerId, string $vendorId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO address_entity (
                id, owner_id, vendor_id, line1, city, country_code, validation_status, created_at
            ) VALUES (
                :id, :owner_id, :vendor_id, :line1, :city, :country_code, :validation_status, :created_at
            )'
        );

        self::assertNotFalse($stmt);
        $stmt->execute([
            ':id' => $id,
            ':owner_id' => $ownerId,
            ':vendor_id' => $vendorId,
            ':line1' => '123 Main St',
            ':city' => 'Houston',
            ':country_code' => 'US',
            ':validation_status' => 'pending',
            ':created_at' => '2025-01-01 00:00:00+00:00',
        ]);
    }

    /** @return array<string, mixed> */
    private function fetchAssocRow(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    /** @return array<string, mixed> */
    private function decodeJsonObject(string $json): array
    {
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @param array<string, mixed> $row */
    private function stringField(array $row, string $key): string
    {
        self::assertArrayHasKey($key, $row);
        self::assertIsString($row[$key]);

        return $row[$key];
    }

    /** @param array<string, mixed> $row */
    private function intField(array $row, string $key): int
    {
        self::assertArrayHasKey($key, $row);
        $value = $row[$key];
        if (is_int($value)) {
            return $value;
        }

        self::assertIsString($value);
        self::assertTrue(is_numeric($value));

        return (int) $value;
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
  source_system TEXT NULL,
  source_type TEXT NULL,
  source_reference TEXT NULL,
  normalization_version TEXT NULL,
  raw_input_snapshot TEXT NULL,
  normalized_snapshot TEXT NULL,
  provider_digest TEXT NULL,
  governance_status TEXT NOT NULL DEFAULT 'canonical',
  duplicate_of_id TEXT NULL,
  superseded_by_id TEXT NULL,
  alias_of_id TEXT NULL,
  conflict_with_id TEXT NULL,
  revalidation_due_at TEXT NULL,
  revalidation_policy TEXT NULL,
  last_validation_provider TEXT NULL,
  last_validation_status TEXT NULL,
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
);

CREATE TABLE address_evidence_snapshot (
  id TEXT PRIMARY KEY,
  address_id TEXT NOT NULL,
  owner_id TEXT NULL,
  vendor_id TEXT NULL,
  source_system TEXT NULL,
  source_type TEXT NULL,
  source_reference TEXT NULL,
  validated_by TEXT NULL,
  validated_at TEXT NULL,
  normalization_version TEXT NULL,
  raw_input_snapshot TEXT NULL,
  normalized_snapshot TEXT NULL,
  validation_status TEXT NOT NULL,
  validation_score INTEGER NULL,
  validation_issues TEXT NULL,
  provider_digest TEXT NULL,
  created_at TEXT NOT NULL
);

CREATE INDEX address_evidence_snapshot_address_idx
  ON address_evidence_snapshot (address_id, created_at DESC, id DESC);

CREATE TABLE address_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event_name TEXT NOT NULL,
  event_version INTEGER NOT NULL,
  payload TEXT NOT NULL
);
SQL;
    }
}
