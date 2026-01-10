# Postgres migrations

Apply these migrations in order. Later migrations depend on objects created by earlier ones.

| Order | File | Depends on | Purpose |
| --- | --- | --- | --- |
| 001 | `001_address.sql` | none | Base address schema, triggers, and supporting indexes. |
| 002 | `002_address_validation_verdict.sql` | `001_address.sql` | Validation verdict storage fields and indexes. |

## Conventions

- Use a three-digit version prefix in the filename.
- Add a header comment to each migration with `Version`, `Depends on`, and idempotency notes.
- Keep migrations idempotent so they can be safely re-run during bootstrap or recovery.
