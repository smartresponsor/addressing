-- Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
-- Address data domain schema (Postgres)

CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS address_entity
(
    id                  CHAR(26) PRIMARY KEY,
    owner_id            VARCHAR(64)      NULL,
    vendor_id           VARCHAR(64)      NULL,
    tag                 VARCHAR(64)      NULL,
    name                VARCHAR(128)     NULL,
    company             VARCHAR(128)     NULL,
    phone               VARCHAR(64)      NULL,
    email               VARCHAR(128)     NULL,
    raw                 JSONB            NULL,
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

    dedupe_key          VARCHAR(128)     NULL,

    created_at          TIMESTAMPTZ      NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ      NULL,
    deleted_at          TIMESTAMPTZ      NULL
);

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
    published_at  TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS address_outbox_pub_idx ON address_outbox (published_at);


-- Checks for validation_status and country_code
ALTER TABLE address_entity
    ADD CONSTRAINT address_country_len_chk CHECK (char_length(country_code) = 2);

ALTER TABLE address_entity
    ADD CONSTRAINT address_validation_status_chk CHECK (validation_status IN ('unknown', 'normalized', 'validated'));

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
    ADD COLUMN IF NOT EXISTS published_attempt INT  NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_error        TEXT NULL;
