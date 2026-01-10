<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */

declare(strict_types=1);

namespace Tests\Addressing;

use App\Entity\Address\AddressData;
use App\Repository\Address\AddressRepository;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 *
 */

/**
 *
 */
abstract class AddressRepositoryTestCase extends TestCase
{
    protected PDO $pdo;
    protected AddressRepository $repo;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->pdo = new class('sqlite::memory:') extends PDO {
            /**
             * @param string $statement
             * @param array<int, mixed> $driver_options
             * @return \PDOStatement|false
             */
            public function prepare($statement, $driver_options = []): PDOStatement|false
            {
                $statement = str_replace('::jsonb', '', $statement);
                return parent::prepare($statement, $driver_options);
            }
        };
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (method_exists($this->pdo, 'sqliteCreateFunction')) {
            $this->pdo->sqliteCreateFunction('now', static fn (): string => (new DateTimeImmutable())->format(DATE_ATOM));
        }
        $this->createSchema($this->pdo);
        $this->repo = new AddressRepository($this->pdo);
    }

    /**
     * @param \PDO $pdo
     * @return void
     */
    private function createSchema(PDO $pdo): void
    {
        $sql = <<<SQL
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
        $pdo->exec($sql);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return \App\Entity\Address\AddressData
     */
    protected function makeAddress(array $overrides = []): AddressData
    {
        $data = array_merge([
            'id' => 'addr-1',
            'ownerId' => 'owner-1',
            'vendorId' => 'vendor-1',
            'line1' => '123 Main St',
            'line2' => 'Suite 5',
            'city' => 'Houston',
            'region' => 'TX',
            'postalCode' => '77002',
            'countryCode' => 'US',
            'line1Norm' => '123 MAIN ST',
            'cityNorm' => 'HOUSTON',
            'regionNorm' => 'TX',
            'postalCodeNorm' => '77002',
            'latitude' => 29.7604,
            'longitude' => -95.3698,
            'geohash' => '9vk1m',
            'validationStatus' => 'unknown',
            'validationProvider' => null,
            'validatedAt' => null,
            'dedupeKey' => 'dedupe:1',
            'createdAt' => '2025-01-01T00:00:00Z',
            'updatedAt' => null,
            'deletedAt' => null,
        ], $overrides);

        return new AddressData(
            $data['id'],
            $data['ownerId'],
            $data['vendorId'],
            $data['line1'],
            $data['line2'],
            $data['city'],
            $data['region'],
            $data['postalCode'],
            $data['countryCode'],
            $data['line1Norm'],
            $data['cityNorm'],
            $data['regionNorm'],
            $data['postalCodeNorm'],
            $data['latitude'],
            $data['longitude'],
            $data['geohash'],
            $data['validationStatus'],
            $data['validationProvider'],
            $data['validatedAt'],
            $data['dedupeKey'],
            $data['createdAt'],
            $data['updatedAt'],
            $data['deletedAt']
        );
    }
}
