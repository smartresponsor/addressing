-- AddressData: store provider raw response and normalized verdict.
-- Safe to re-run (idempotent via IF NOT EXISTS).

ALTER TABLE address_entity
    ADD COLUMN IF NOT EXISTS validation_raw         JSONB       NULL,
    ADD COLUMN IF NOT EXISTS validation_verdict     JSONB       NULL,
    ADD COLUMN IF NOT EXISTS validation_deliverable BOOLEAN     NULL,
    ADD COLUMN IF NOT EXISTS validation_granularity VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS validation_quality     INT         NULL;

CREATE INDEX IF NOT EXISTS idx_address_entity_validation_deliverable
    ON address_entity (validation_deliverable)
    WHERE validation_deliverable IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_address_entity_validation_quality
    ON address_entity (validation_quality)
    WHERE validation_quality IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_address_entity_validation_granularity
    ON address_entity (validation_granularity)
    WHERE validation_granularity IS NOT NULL;
