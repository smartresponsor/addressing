-- Migration: address entity contract synchronization (Postgres)
-- Version: 002
-- Depends on: 001_address.sql
-- Idempotent: yes
-- Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

ALTER TABLE IF EXISTS address_entity
    ADD COLUMN IF NOT EXISTS validation_fingerprint VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS validation_raw JSONB NULL,
    ADD COLUMN IF NOT EXISTS validation_verdict JSONB NULL,
    ADD COLUMN IF NOT EXISTS validation_deliverable BOOLEAN NULL,
    ADD COLUMN IF NOT EXISTS validation_granularity VARCHAR(32) NULL,
    ADD COLUMN IF NOT EXISTS validation_quality INT NULL,
    ADD COLUMN IF NOT EXISTS revalidation_due_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS revalidation_policy VARCHAR(16) NULL,
    ADD COLUMN IF NOT EXISTS last_validation_provider VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS last_validation_status VARCHAR(16) NULL,
    ADD COLUMN IF NOT EXISTS last_validation_score INT NULL;

DO
$$
BEGIN
    ALTER TABLE address_entity
        ADD CONSTRAINT address_country_len_chk CHECK (char_length(country_code) = 2);
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

DO
$$
BEGIN
    ALTER TABLE address_entity
        ADD CONSTRAINT address_validation_status_chk CHECK (validation_status IN ('unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden'));
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

DO
$$
BEGIN
    ALTER TABLE address_entity
        ADD CONSTRAINT address_source_type_chk CHECK (source_type IS NULL OR source_type IN ('manual', 'import', 'partner', 'validator', 'override', 'migration'));
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

DO
$$
BEGIN
    ALTER TABLE address_entity
        ADD CONSTRAINT address_governance_status_chk CHECK (governance_status IN ('canonical', 'duplicate', 'superseded', 'alias', 'conflict'));
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

DO
$$
BEGIN
    ALTER TABLE address_entity
        ADD CONSTRAINT address_revalidation_policy_chk CHECK (revalidation_policy IS NULL OR revalidation_policy IN ('manual', 'on-change', 'daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual'));
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

DO
$$
BEGIN
    ALTER TABLE address_entity
        ADD CONSTRAINT address_last_validation_status_chk CHECK (last_validation_status IS NULL OR last_validation_status IN ('normalized', 'validated', 'rejected', 'uncertain', 'overridden'));
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

DO
$$
BEGIN
    ALTER TABLE address_evidence_snapshot
        ADD CONSTRAINT address_evidence_source_type_chk CHECK (source_type IS NULL OR source_type IN ('manual', 'import', 'partner', 'validator', 'override', 'migration'));
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

DO
$$
BEGIN
    ALTER TABLE address_evidence_snapshot
        ADD CONSTRAINT address_evidence_validation_status_chk CHECK (validation_status IN ('unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden'));
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

CREATE INDEX IF NOT EXISTS address_validation_fp_idx ON address_entity (validation_fingerprint);
CREATE INDEX IF NOT EXISTS address_governance_status_idx ON address_entity (governance_status);
CREATE INDEX IF NOT EXISTS address_revalidation_due_at_idx ON address_entity (revalidation_due_at);
CREATE INDEX IF NOT EXISTS address_last_validation_status_idx ON address_entity (last_validation_status);
CREATE INDEX IF NOT EXISTS address_duplicate_of_idx ON address_entity (duplicate_of_id) WHERE duplicate_of_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS address_superseded_by_idx ON address_entity (superseded_by_id) WHERE superseded_by_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS address_alias_of_idx ON address_entity (alias_of_id) WHERE alias_of_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS address_conflict_with_idx ON address_entity (conflict_with_id) WHERE conflict_with_id IS NOT NULL;
