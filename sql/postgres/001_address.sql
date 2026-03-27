-- Migration: address data domain schema (Postgres)
-- Version: 001
-- Depends on: none
-- Idempotent: yes (IF NOT EXISTS / CREATE OR REPLACE)
-- Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS address_entity
(
    id                  CHAR(26) PRIMARY KEY,
    owner_id            VARCHAR(64)      NULL,
    vendor_id           VARCHAR(64)      NULL,
    line1               VARCHAR(256)     NOT NULL,
    line2               VARCHAR(256)     NULL,
    city                VARCHAR(128)     NOT NULL,
    region              VARCHAR(128)     NULL,
    postal_code         VARCHAR(32)      NULL,
    country_code        CHAR(2)          NOT NULL,

    line1_norm          VARCHAR(256)     NULL,
    city_norm           VARCHAR(128)     NULL,
    region_norm         VARCHAR(128)     NULL,
    postal_code_norm    VARCHAR(32)      NULL,

    latitude            DOUBLE PRECISION NULL,
    longitude           DOUBLE PRECISION NULL,
    geohash             VARCHAR(32)      NULL,

    validation_status   VARCHAR(16)      NOT NULL DEFAULT 'unknown',
    validation_provider VARCHAR(64)      NULL,
    validated_at        TIMESTAMPTZ      NULL,
    source_system       VARCHAR(64)      NULL,
    source_type         VARCHAR(32)      NULL,
    source_reference    VARCHAR(128)     NULL,
    normalization_version VARCHAR(64)    NULL,
    raw_input_snapshot  JSONB            NULL,
    normalized_snapshot JSONB            NULL,
    provider_digest     VARCHAR(64)      NULL,
    governance_status   VARCHAR(16)      NOT NULL DEFAULT 'canonical',
    duplicate_of_id     CHAR(26)         NULL,
    superseded_by_id    CHAR(26)         NULL,
    alias_of_id         CHAR(26)         NULL,
    conflict_with_id    CHAR(26)         NULL,

    dedupe_key          VARCHAR(128)     NULL,

    created_at          TIMESTAMPTZ      NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ      NULL,
    deleted_at          TIMESTAMPTZ      NULL
);

DO
$$
BEGIN
    ALTER TABLE address_entity
        ADD CONSTRAINT address_tenant_scope_chk CHECK (owner_id IS NOT NULL OR vendor_id IS NOT NULL);
EXCEPTION
    WHEN duplicate_object THEN
        NULL;
END
$$;

CREATE INDEX IF NOT EXISTS address_owner_idx ON address_entity (owner_id);
CREATE INDEX IF NOT EXISTS address_vendor_idx ON address_entity (vendor_id);
CREATE INDEX IF NOT EXISTS address_country_idx ON address_entity (country_code);
CREATE INDEX IF NOT EXISTS address_city_idx ON address_entity (city);
CREATE INDEX IF NOT EXISTS address_status_idx ON address_entity (validation_status);
CREATE UNIQUE INDEX IF NOT EXISTS address_dedupe_unique ON address_entity (dedupe_key) WHERE dedupe_key IS NOT NULL;

CREATE OR REPLACE FUNCTION address_touch_updated_at()
    RETURNS trigger AS
$$
BEGIN
    NEW.updated_at := now();
    RETURN NEW;
END
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_address_touch_updated_at ON address_entity;
CREATE TRIGGER trg_address_touch_updated_at
    BEFORE UPDATE
    ON address_entity
    FOR EACH ROW
EXECUTE FUNCTION address_touch_updated_at();

-- Outbox table for Address domain
CREATE TABLE IF NOT EXISTS address_outbox
(
    id            BIGSERIAL PRIMARY KEY,
    stream        VARCHAR(64) NOT NULL DEFAULT 'address',
    event_name    VARCHAR(64) NOT NULL,
    event_version INT         NOT NULL DEFAULT 1,
    payload       JSONB       NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    published_at  TIMESTAMPTZ NULL,
    locked_at     TIMESTAMPTZ NULL,
    locked_by     VARCHAR(64) NULL
);

CREATE INDEX IF NOT EXISTS address_outbox_pub_idx ON address_outbox (published_at);


CREATE TABLE IF NOT EXISTS address_evidence_snapshot
(
    id                    CHAR(32) PRIMARY KEY,
    address_id            CHAR(26)         NOT NULL REFERENCES address_entity (id) ON DELETE CASCADE,
    owner_id              VARCHAR(64)      NULL,
    vendor_id             VARCHAR(64)      NULL,
    source_system         VARCHAR(64)      NULL,
    source_type           VARCHAR(32)      NULL,
    source_reference      VARCHAR(128)     NULL,
    validated_by          VARCHAR(64)      NULL,
    validated_at          TIMESTAMPTZ      NULL,
    normalization_version VARCHAR(64)      NULL,
    raw_input_snapshot    JSONB            NULL,
    normalized_snapshot   JSONB            NULL,
    validation_status     VARCHAR(16)      NOT NULL DEFAULT 'unknown',
    validation_score      INT              NULL,
    validation_issues     JSONB            NULL,
    provider_digest       VARCHAR(64)      NULL,
    created_at            TIMESTAMPTZ      NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS address_evidence_snapshot_address_idx
    ON address_evidence_snapshot (address_id, created_at DESC, id DESC);
CREATE INDEX IF NOT EXISTS address_evidence_snapshot_owner_idx ON address_evidence_snapshot (owner_id);
CREATE INDEX IF NOT EXISTS address_evidence_snapshot_vendor_idx ON address_evidence_snapshot (vendor_id);

ALTER TABLE address_evidence_snapshot
    ADD CONSTRAINT address_evidence_source_type_chk CHECK (source_type IS NULL OR source_type IN ('manual', 'import', 'partner', 'validator', 'override', 'migration'));

ALTER TABLE address_evidence_snapshot
    ADD CONSTRAINT address_evidence_validation_status_chk CHECK (validation_status IN ('unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden'));


-- Checks for validation_status and country_code
ALTER TABLE address_entity
    ADD CONSTRAINT address_country_len_chk CHECK (char_length(country_code) = 2);

ALTER TABLE address_entity
    ADD CONSTRAINT address_validation_status_chk CHECK (validation_status IN ('unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden'));

ALTER TABLE address_entity
    ADD CONSTRAINT address_source_type_chk CHECK (source_type IS NULL OR source_type IN ('manual', 'import', 'partner', 'validator', 'override', 'migration'));

ALTER TABLE address_entity
    ADD CONSTRAINT address_governance_status_chk CHECK (governance_status IN ('canonical', 'duplicate', 'superseded', 'alias', 'conflict'));

ALTER TABLE address_entity
    ADD CONSTRAINT address_revalidation_policy_chk CHECK (revalidation_policy IS NULL OR revalidation_policy IN ('manual', 'on-change', 'daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual'));

ALTER TABLE address_entity
    ADD CONSTRAINT address_last_validation_status_chk CHECK (last_validation_status IS NULL OR last_validation_status IN ('normalized', 'validated', 'rejected', 'uncertain', 'overridden'));

-- Canonical key generator: produce stable dedupe string from normalized fields if present
CREATE OR REPLACE FUNCTION address_canonical_key(
    _line1_norm text, _city_norm text, _region_norm text, _postal_norm text, _cc text
) RETURNS text AS
$$
DECLARE
    s text;
BEGIN
    s := coalesce(lower(regexp_replace(_line1_norm, '\s+', '', 'g')), '') || '|' ||
         coalesce(lower(regexp_replace(_city_norm, '\s+', '', 'g')), '') || '|' ||
         coalesce(lower(regexp_replace(_region_norm, '\s+', '', 'g')), '') || '|' ||
         coalesce(lower(regexp_replace(_postal_norm, '\s+', '', 'g')), '') || '|' ||
         coalesce(upper(_cc), '');
    IF s = '||||' THEN
        RETURN NULL;
    END IF;
    RETURN s;
END
$$ LANGUAGE plpgsql IMMUTABLE;

-- Trigger to auto-fill dedupe_key when null and normalized fields are present
CREATE OR REPLACE FUNCTION address_dedupe_autofill()
    RETURNS trigger AS
$$
DECLARE
    k text;
BEGIN
    IF NEW.dedupe_key IS NULL THEN
        k := address_canonical_key(NEW.line1_norm, NEW.city_norm, NEW.region_norm, NEW.postal_code_norm,
                                   NEW.country_code);
        IF k IS NOT NULL THEN
            NEW.dedupe_key := k;
        END IF;
    END IF;
    RETURN NEW;
END
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_address_dedupe_autofill ON address_entity;
CREATE TRIGGER trg_address_dedupe_autofill
    BEFORE INSERT OR UPDATE
    ON address_entity
    FOR EACH ROW
EXECUTE FUNCTION address_dedupe_autofill();


-- ===== RC-2: Operational data enhancements =====

-- Trigram search for free-text q
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX IF NOT EXISTS address_q_trgm_idx
    ON address_entity
        USING gin ((lower(line1 || ' ' || city || ' ' || coalesce(postal_code, ''))) gin_trgm_ops);

-- Composite indices for common filters
CREATE INDEX IF NOT EXISTS address_country_city_id_idx ON address_entity (country_code, city, id);
CREATE INDEX IF NOT EXISTS address_owner_id_idx ON address_entity (owner_id, id);
CREATE INDEX IF NOT EXISTS address_vendor_id_idx ON address_entity (vendor_id, id);

-- Audit log
CREATE TABLE IF NOT EXISTS address_audit
(
    id         BIGSERIAL PRIMARY KEY,
    op         CHAR(1)     NOT NULL, -- I/U/D
    row_old    JSONB       NULL,
    row_new    JSONB       NULL,
    changed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE OR REPLACE FUNCTION address_audit_func()
    RETURNS trigger AS
$$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO address_audit(op, row_old, row_new) VALUES ('I', NULL, to_jsonb(NEW));
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO address_audit(op, row_old, row_new) VALUES ('U', to_jsonb(OLD), to_jsonb(NEW));
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO address_audit(op, row_old, row_new) VALUES ('D', to_jsonb(OLD), NULL);
        RETURN OLD;
    END IF;
    RETURN NULL;
END
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_address_audit_ins ON address_entity;
DROP TRIGGER IF EXISTS trg_address_audit_upd ON address_entity;
DROP TRIGGER IF EXISTS trg_address_audit_del ON address_entity;

CREATE TRIGGER trg_address_audit_ins
    AFTER INSERT
    ON address_entity
    FOR EACH ROW
EXECUTE FUNCTION address_audit_func();

CREATE TRIGGER trg_address_audit_upd
    AFTER UPDATE
    ON address_entity
    FOR EACH ROW
EXECUTE FUNCTION address_audit_func();

CREATE TRIGGER trg_address_audit_del
    AFTER DELETE
    ON address_entity
    FOR EACH ROW
EXECUTE FUNCTION address_audit_func();


-- ===== RC-3: Reliability and idempotency =====

-- Idempotency: remember last validation fingerprint per row
ALTER TABLE IF EXISTS address_entity
    ADD COLUMN IF NOT EXISTS validation_fingerprint VARCHAR(64) NULL;
CREATE INDEX IF NOT EXISTS address_validation_fp_idx ON address_entity (validation_fingerprint);

-- Outbox: attempts and last error for robust draining
ALTER TABLE IF EXISTS address_outbox
    ADD COLUMN IF NOT EXISTS locked_at        TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS locked_by        VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS published_attempt INT  NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_error        TEXT NULL;


CREATE INDEX IF NOT EXISTS address_governance_status_idx ON address_entity (governance_status);
CREATE INDEX IF NOT EXISTS address_revalidation_due_at_idx ON address_entity (revalidation_due_at);

CREATE INDEX IF NOT EXISTS address_evidence_snapshot_validation_status_created_idx
    ON address_evidence_snapshot (validation_status, created_at DESC, id DESC);
CREATE INDEX IF NOT EXISTS address_evidence_snapshot_address_validation_status_idx
    ON address_evidence_snapshot (address_id, validation_status, created_at DESC, id DESC);
CREATE INDEX IF NOT EXISTS address_entity_review_queue_idx
    ON address_entity (deleted_at, governance_status, revalidation_due_at, id);
CREATE INDEX IF NOT EXISTS address_entity_validation_review_idx
    ON address_entity (deleted_at, validation_status, last_validation_status, id);
CREATE INDEX IF NOT EXISTS address_entity_normalization_version_idx
    ON address_entity (deleted_at, normalization_version, id);
CREATE INDEX IF NOT EXISTS address_last_validation_status_idx ON address_entity (last_validation_status);
CREATE INDEX IF NOT EXISTS address_duplicate_of_idx ON address_entity (duplicate_of_id) WHERE duplicate_of_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS address_superseded_by_idx ON address_entity (superseded_by_id) WHERE superseded_by_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS address_alias_of_idx ON address_entity (alias_of_id) WHERE alias_of_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS address_conflict_with_idx ON address_entity (conflict_with_id) WHERE conflict_with_id IS NOT NULL;
