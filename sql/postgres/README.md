# Postgres migrations

Apply these migrations in order. Later migrations depend on objects created by earlier ones.

| Order | File | Depends on | Purpose |
| --- | --- | --- | --- |
| 001 | `001_address.sql` | none | Base address schema, triggers, outbox, audit, and supporting indexes. |
| 002 | `002_address_validation_verdict.sql` | `001_address.sql` | Validation verdict storage fields and indexes. |
| 003 | `002_address_entity_contract_sync.sql` | `001_address.sql`, `002_address_validation_verdict.sql` | Synchronize Postgres with the current entity/runtime contract (validation lineage, governance, revalidation, last-validation fields). |
| 004 | `003_address_outbox_runtime_sync.sql` | `001_address.sql`, `002_address_entity_contract_sync.sql` | Synchronize outbox retry/runtime columns (`published_attempt`, `last_error`). |

## Auxiliary SQL assets

- `seed_demo.sql` is a manual demo/sample seed and is **not** part of ordered migration authority.

## Conventions

- Use a three-digit version prefix in the filename.
- Add a header comment to each migration with `Version`, `Depends on`, and idempotency notes.
- Keep migrations idempotent so they can be safely re-run during bootstrap or recovery.
- Do not rely on filename sort alone as the only semantic source of order; keep this README aligned with the actual migration contract whenever a synchronization migration is added.
