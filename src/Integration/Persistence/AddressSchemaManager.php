<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Persistence;

final class AddressSchemaManager
{
    public static function ensureSchema(\PDO $pdo, string $projectDir): void
    {
        $driver = self::driver($pdo);
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
        $driver = self::driver($pdo);
        if ('sqlite' === $driver) {
            $pdo->exec('DROP TRIGGER IF EXISTS trg_address_touch_updated_at');
            $pdo->exec('DROP TRIGGER IF EXISTS trg_address_dedupe_autofill');
            $pdo->exec('DROP TRIGGER IF EXISTS trg_address_dedupe_autofill_update');
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
  dedupe_key TEXT NULL,
  validation_fingerprint TEXT NULL,
  validation_raw TEXT NULL,
  validation_verdict TEXT NULL,
  validation_deliverable INTEGER NULL,
  validation_granularity TEXT NULL,
  validation_quality INTEGER NULL,
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
  created_at TEXT NOT NULL,
  updated_at TEXT NULL,
  deleted_at TEXT NULL,
  CONSTRAINT address_tenant_scope_chk CHECK (owner_id IS NOT NULL OR vendor_id IS NOT NULL)
);
SQL
        );

        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS address_dedupe_unique ON address_entity (dedupe_key) WHERE dedupe_key IS NOT NULL');

        $pdo->exec(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS trg_address_touch_updated_at
  AFTER UPDATE ON address_entity
  FOR EACH ROW
  WHEN NEW.updated_at IS OLD.updated_at
BEGIN
  UPDATE address_entity
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.id;
END;
SQL
        );

        $pdo->exec(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS trg_address_dedupe_autofill
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
SQL
        );

        $pdo->exec(<<<'SQL'
CREATE TRIGGER IF NOT EXISTS trg_address_dedupe_autofill_update
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
SQL
        );

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
  validation_status TEXT NOT NULL
    CHECK (validation_status IN ('unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden')),
  validation_score INTEGER NULL,
  validation_issues TEXT NULL,
  provider_digest TEXT NULL,
  created_at TEXT NOT NULL,
  CONSTRAINT address_evidence_snapshot_scope_chk CHECK (owner_id IS NOT NULL OR vendor_id IS NOT NULL)
);
SQL
        );

        $pdo->exec('CREATE INDEX IF NOT EXISTS address_evidence_snapshot_address_idx ON address_evidence_snapshot (address_id, created_at DESC, id DESC)');

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS address_outbox (
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
SQL
        );
    }

    private static function driver(\PDO $pdo): string
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driver) ? $driver : '';
    }
}
