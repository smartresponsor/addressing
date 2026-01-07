# SmartResponsor Address — RC-A Baseline (Data Domain)

Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

**Address is data-only.** No Locator logic inside. No controllers needed if your platform autogenerates CRUD from schema.
This package contains:
- Postgres schema (`sql/postgres/001_address.sql`) with outbox table.
- MySQL read projection (`sql/mysql/001_projection.sql`).
- PHP 8.2 data classes and repositories (PDO-based).
- CLI: `bin/address-migrate` (apply schema), `bin/address-projection-runner` (sync projection).

## Quick start
1) Copy `.env.dist` → `.env` and export variables (PG_*, MY_*).
2) `php bin/address-migrate`
3) Use `SmartResponsor\Layer\Address\AddressRepository` in your bootstrap to persist data.
4) Run `php bin/address-projection-runner` to refresh MySQL projection.

## Canon
- Layer-first path: `src/Entity/Address/Address*.php`; mirror interface in `src/Contract/Address/`.
- No plural in class or method names.
- Postgres for Data, MySQL for Infrastructure.
- English comments only.


## AddressValidated ingestion (from Locator)
Use CLI to apply validated data produced by Locator (no Locator logic inside):
```bash
# NDJSON: one JSON per line; each object must include "id"
php bin/address-apply-validated validated.ndjson

# Single JSON object or array:
php bin/address-apply-validated validated.json
```
The applier updates normalized/geo fields, sets `validation_status=validated`, touches `updated_at` and appends AddressUpdated to outbox.

## Dedupe key
- A canonical key is generated automatically when normalized fields are present (see `address_canonical_key()` and trigger `trg_address_dedupe_autofill`).
- You may still supply explicit `dedupeKey` from Locator; Address will store it.


## RC-2 — Operational data
- Trigram search index for free text `q` (Postgres `pg_trgm`).
- Composite indices for common filters.
- Full audit log (`address_audit`) via triggers on INSERT/UPDATE/DELETE.
- Cleanup TTL for soft-deleted rows (`bin/address-cleanup-soft-delete`), TTL via `ADDRESS_DELETE_TTL_DAYS` (default 30).
- Dedupe backfill utility (`bin/address-backfill-dedupe`).
- Incremental projection sync since timestamp (`bin/address-projection-sync-since <ISO8601>`).


## RC-3 — Reliability and idempotency
- Idempotent `AddressValidatedApplier` (fingerprint of payload; no-op on repeat).
- Postgres: column `validation_fingerprint` on `address_entity`.
- Outbox robust draining: `address_outbox` keeps `published_attempt` and `last_error`.
- CLI `bin/address-outbox-drain` pushes events to `OUTBOX_WEBHOOK_URL` with retry/backoff.
- Metrics exporter `bin/address-metric-export` prints Prometheus exposition format to stdout.


## GA — Hardening
- Audit retention tool with TTL in days: `bin/address-audit-retention`.
- Projection daemon with durable watermark: `bin/address-projection-daemon`.
- Index policy CLI to enable/disable trigram and composite index set: `bin/address-index-policy`.
- Install script for on‑prem bootstrap (Postgres + MySQL): `bin/address-migrate` + `bin/address-projection-migrate`.


## Mirror interfaces per Symfony layer
- `src/EntityInterface/Address/AddressInterface.php`
- `src/RepositoryInterface/Address/AddressRepositoryInterface.php`
- `src/ServiceInterface/Address/AddressProjectionInterface.php`
- `src/ServiceInterface/Address/AddressValidatedApplierInterface.php`
- `src/ServiceInterface/Address/AddressOutboxDrainerInterface.php`
- `src/UtilInterface/Address/AddressUlidInterface.php`
Classes implement their mirrors accordingly.

## Operations
- Migration runbook: `docs/ops/migration.md`
- Rollback runbook: `docs/ops/rollback.md`
- Recovery runbook: `docs/ops/recovery.md`
