<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Persistence;

final class AddressSchemaManager
{
    public static function ensureSchema(\PDO $pdo, string $projectDir): void
    {
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ('sqlite' === $driver) {
            self::ensureSqliteSchema($pdo);

            return;
        }

        if ('pgsql' === $driver) {
            self::ensurePostgresSchema($pdo, $projectDir);
        }
    }

    public static function resetSchema(\PDO $pdo, string $projectDir): void
    {
        $driver = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ('sqlite' === $driver) {
            $pdo->exec('DROP TABLE IF EXISTS address_outbox');
            $pdo->exec('DROP TABLE IF EXISTS address_evidence_snapshot');
            $pdo->exec('DROP TABLE IF EXISTS address_entity');
            self::ensureSqliteSchema($pdo);

            return;
        }

        if ('pgsql' === $driver) {
            $pdo->exec('DROP TABLE IF EXISTS address_outbox');
            $pdo->exec('DROP TABLE IF EXISTS address_evidence_snapshot');
            $pdo->exec('DROP TABLE IF EXISTS address_entity');
            self::ensurePostgresSchema($pdo, $projectDir);
        }
    }

    private static function ensurePostgresSchema(\PDO $pdo, string $projectDir): void
    {
        $sqlDir = $projectDir.'/sql/postgres';
        $files = glob($sqlDir.'/*.sql');
        if (!is_array($files)) {
            return;
        }

        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if (false === $sql) {
                throw new \RuntimeException('failed_to_read_schema_'.$file);
            }

            $pdo->exec($sql);
        }
    }

    private static function ensureSqliteSchema(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS address_entity (
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
  validation_fingerprint TEXT NULL,
  validation_raw TEXT NULL,
  validation_verdict TEXT NULL,
  validation_deliverable INTEGER NULL,
  validation_granularity TEXT NULL,
  validation_quality INTEGER NULL,
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
  created_at TEXT NOT NULL,
  updated_at TEXT NULL,
  deleted_at TEXT NULL
);
SQL
        );

        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS address_entity_dedupe_uq ON address_entity (dedupe_key) WHERE dedupe_key IS NOT NULL');

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS address_evidence_snapshot (
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
SQL
        );

        $pdo->exec('CREATE INDEX IF NOT EXISTS address_evidence_snapshot_address_idx ON address_evidence_snapshot (address_id, created_at DESC, id DESC)');

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS address_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event_name TEXT NOT NULL,
  event_version INTEGER NOT NULL,
  payload TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at TEXT NULL,
  published_attempt INTEGER NOT NULL DEFAULT 0,
  locked_at TEXT NULL,
  locked_by TEXT NULL,
  last_error TEXT NULL
);
SQL
        );
    }
}
