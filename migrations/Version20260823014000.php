<?php

declare(strict_types=1);

namespace App\Addressing\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823014000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the canonical AddressEntity PostgreSQL table from current Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS address_entity (
    id VARCHAR(26) NOT NULL,
    owner_id VARCHAR(64) DEFAULT NULL,
    vendor_id VARCHAR(64) DEFAULT NULL,
    line1 VARCHAR(256) NOT NULL,
    line2 VARCHAR(256) DEFAULT NULL,
    city VARCHAR(128) NOT NULL,
    region VARCHAR(128) DEFAULT NULL,
    postal_code VARCHAR(32) DEFAULT NULL,
    country_code VARCHAR(2) NOT NULL,
    line1_norm VARCHAR(256) DEFAULT NULL,
    city_norm VARCHAR(128) DEFAULT NULL,
    region_norm VARCHAR(128) DEFAULT NULL,
    postal_code_norm VARCHAR(32) DEFAULT NULL,
    latitude DOUBLE PRECISION DEFAULT NULL,
    longitude DOUBLE PRECISION DEFAULT NULL,
    geohash VARCHAR(32) DEFAULT NULL,
    validation_status VARCHAR(16) DEFAULT 'unknown' NOT NULL,
    validation_provider VARCHAR(64) DEFAULT NULL,
    validated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    dedupe_key VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    validation_fingerprint VARCHAR(64) DEFAULT NULL,
    validation_raw JSON DEFAULT NULL,
    validation_verdict JSON DEFAULT NULL,
    validation_deliverable BOOLEAN DEFAULT NULL,
    validation_granularity VARCHAR(64) DEFAULT NULL,
    validation_quality INT DEFAULT NULL,
    source_system VARCHAR(64) DEFAULT NULL,
    source_type VARCHAR(32) DEFAULT NULL,
    source_reference VARCHAR(128) DEFAULT NULL,
    normalization_version VARCHAR(64) DEFAULT NULL,
    raw_input_snapshot JSON DEFAULT NULL,
    normalized_snapshot JSON DEFAULT NULL,
    provider_digest VARCHAR(64) DEFAULT NULL,
    governance_status VARCHAR(16) DEFAULT 'canonical' NOT NULL,
    duplicate_of_id VARCHAR(26) DEFAULT NULL,
    superseded_by_id VARCHAR(26) DEFAULT NULL,
    alias_of_id VARCHAR(26) DEFAULT NULL,
    conflict_with_id VARCHAR(26) DEFAULT NULL,
    revalidation_due_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    revalidation_policy VARCHAR(32) DEFAULT NULL,
    last_validation_provider VARCHAR(64) DEFAULT NULL,
    last_validation_status VARCHAR(16) DEFAULT NULL,
    last_validation_score INT DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS address_owner_idx ON address_entity (owner_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS address_vendor_idx ON address_entity (vendor_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS address_country_idx ON address_entity (country_code)');
        $this->addSql('CREATE INDEX IF NOT EXISTS address_city_idx ON address_entity (city)');
        $this->addSql('CREATE INDEX IF NOT EXISTS address_status_idx ON address_entity (validation_status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS address_governance_status_idx ON address_entity (governance_status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS address_revalidation_due_at_idx ON address_entity (revalidation_due_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS address_last_validation_status_idx ON address_entity (last_validation_status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS address_validation_fp_idx ON address_entity (validation_fingerprint)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS address_dedupe_unique ON address_entity (dedupe_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS address_entity');
    }
}
