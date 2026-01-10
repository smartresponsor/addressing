-- AddressData: store contact and raw payload fields.
-- Safe to re-run (idempotent via IF NOT EXISTS).

ALTER TABLE address_entity
    ADD COLUMN IF NOT EXISTS tag     VARCHAR(64)  NULL,
    ADD COLUMN IF NOT EXISTS name    VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS company VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS phone   VARCHAR(64)  NULL,
    ADD COLUMN IF NOT EXISTS email   VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS raw     JSONB        NULL;
