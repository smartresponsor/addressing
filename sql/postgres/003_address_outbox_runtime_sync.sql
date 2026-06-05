-- Migration: address outbox runtime synchronization (Postgres)
-- Version: 003
-- Depends on: 001_address.sql, 002_address_entity_contract_sync.sql
-- Idempotent: yes
-- Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

ALTER TABLE IF EXISTS address_outbox
    ADD COLUMN IF NOT EXISTS published_attempt INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_error TEXT NULL;

CREATE INDEX IF NOT EXISTS address_outbox_retry_idx
    ON address_outbox (published_at, published_attempt);
