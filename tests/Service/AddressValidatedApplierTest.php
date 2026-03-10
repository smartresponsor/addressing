<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Contract\Address\AddressValidated;
use App\Service\Address\AddressValidatedApplier;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AddressValidatedApplierTest extends TestCase
{
    private PDO $pdo;
    private AddressValidatedApplier $applier;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec($this->schemaSql());
        $this->applier = new AddressValidatedApplier($this->pdo);
    }

    public function testApplyWorksOnSqliteWithoutPgsqlLockSyntax(): void
    {
        $this->insertAddress('addr-1', 'owner-1', 'vendor-1');

        $validated = AddressValidated::fromArray([
            'line1Norm' => 'main st',
            'cityNorm' => 'houston',
            'validationProvider' => 'unit',
        ]);

        $this->applier->apply('addr-1', $validated, 'owner-1', 'vendor-1');

        $row = $this->pdo->query("SELECT validation_status, line1_norm FROM address_entity WHERE id = 'addr-1'")
            ->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('validated', $row['validation_status']);
        self::assertSame('main st', $row['line1_norm']);
    }

    public function testApplyRejectsWrongTenantScope(): void
    {
        $this->insertAddress('addr-2', 'owner-A', 'vendor-A');

        $validated = AddressValidated::fromArray([
            'line1Norm' => 'changed',
            'validationProvider' => 'unit',
        ]);

        $this->expectException(RuntimeException::class);
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
  event_name TEXT NOT NULL,
  event_version INTEGER NOT NULL,
  payload TEXT NOT NULL
);
SQL;
    }
}
