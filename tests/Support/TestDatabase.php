<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Support;

use PDO;

final class TestDatabase
{
    public static function createPdo(): PDO
    {
        $dsn = getenv('TEST_DB_DSN');
        $user = getenv('TEST_DB_USER');
        $pass = getenv('TEST_DB_PASS');

        if (is_string($dsn) && $dsn !== '') {
            $pdo = new PDO($dsn, is_string($user) ? $user : null, is_string($pass) ? $pass : null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } else {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return $pdo;
    }

    public static function resetAddressSchema(PDO $pdo): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec('DROP TABLE IF EXISTS address_outbox');
            $pdo->exec('DROP TABLE IF EXISTS address_entity');

            $pdo->exec('CREATE TABLE address_entity (
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
                latitude DOUBLE PRECISION NULL,
                longitude DOUBLE PRECISION NULL,
                geohash TEXT NULL,
                validation_status TEXT NOT NULL,
                validation_provider TEXT NULL,
                validated_at TEXT NULL,
                dedupe_key TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL,
                validation_fingerprint TEXT NULL,
                validation_raw JSONB NULL,
                validation_verdict JSONB NULL,
                validation_deliverable SMALLINT NULL,
                validation_granularity TEXT NULL,
                validation_quality INTEGER NULL,
                validation_raw_sha256 TEXT NULL,
                source_system TEXT NULL,
                source_type TEXT NULL,
                source_reference TEXT NULL,
                normalization_version TEXT NULL,
                raw_input_snapshot JSONB NULL,
                normalized_snapshot JSONB NULL,
                provider_digest TEXT NULL,
                governance_status TEXT NOT NULL DEFAULT \'canonical\',
                duplicate_of_id TEXT NULL,
                superseded_by_id TEXT NULL,
                alias_of_id TEXT NULL,
                conflict_with_id TEXT NULL,
                revalidation_due_at TEXT NULL,
                revalidation_policy TEXT NULL,
                last_validation_provider TEXT NULL,
                last_validation_status TEXT NULL,
                last_validation_score INTEGER NULL
            )');

            $pdo->exec('CREATE TABLE address_outbox (
                id BIGSERIAL PRIMARY KEY,
                event_name TEXT NOT NULL,
                event_version INTEGER NOT NULL,
                payload JSONB NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                published_at TIMESTAMPTZ NULL,
                locked_at TIMESTAMPTZ NULL,
                locked_by TEXT NULL,
                published_attempt INTEGER NOT NULL DEFAULT 0,
                last_error TEXT NULL
            )');

            return;
        }

        $pdo->exec('DROP TABLE IF EXISTS address_outbox');
        $pdo->exec('DROP TABLE IF EXISTS address_entity');

        $pdo->exec('CREATE TABLE address_entity (
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
            validation_quality INTEGER NULL,
            validation_raw_sha256 TEXT NULL,
            source_system TEXT NULL,
            source_type TEXT NULL,
            source_reference TEXT NULL,
            normalization_version TEXT NULL,
            raw_input_snapshot TEXT NULL,
            normalized_snapshot TEXT NULL,
            provider_digest TEXT NULL,
            governance_status TEXT NOT NULL DEFAULT \'canonical\',
            duplicate_of_id TEXT NULL,
            superseded_by_id TEXT NULL,
            alias_of_id TEXT NULL,
            conflict_with_id TEXT NULL,
            revalidation_due_at TEXT NULL,
            revalidation_policy TEXT NULL,
            last_validation_provider TEXT NULL,
            last_validation_status TEXT NULL,
            last_validation_score INTEGER NULL
        )');

        $pdo->exec('CREATE TABLE address_outbox (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_name TEXT NOT NULL,
            event_version INTEGER NOT NULL,
            payload TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            published_at TEXT NULL,
            locked_at TEXT NULL,
            locked_by TEXT NULL,
            published_attempt INTEGER NOT NULL DEFAULT 0,
            last_error TEXT NULL
        )');
    }
}
