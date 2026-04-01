# Address migrations

This page documents how database migrations in the Address domain are ordered, made idempotent, and rolled back.

## Ordering

Postgres schema migrations live in `sql/postgres/` and must be applied in strict ascending order by their numeric prefix (e.g., `001_`, `002_`). The execution order is part of the contract, because later migrations assume earlier objects (tables, functions, extensions) already exist.

When adding a new migration:

- Use the next available three-digit version prefix in the filename.
- Declare any dependencies explicitly in the header comment (see below).
- Update `sql/postgres/README.md` to list the new migration in order.

## Idempotency rules

All migrations must be safe to re-run. Use defensive DDL where possible:

- `CREATE TABLE IF NOT EXISTS` and `CREATE INDEX IF NOT EXISTS`.
- `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` for additive changes.
- `CREATE OR REPLACE FUNCTION` for routines.
- `DROP TRIGGER IF EXISTS` before creating triggers.

If a change cannot be expressed idempotently, split it into multiple migrations or add guard clauses (e.g., DO blocks that check catalog state).

## Rollback strategy

We do not maintain down scripts. Rollbacks are handled with forward-only corrective migrations:

- Revert behavior by adding a new migration that undoes or compensates for the change.
- Preserve data whenever possible (e.g., keep columns but stop using them).
- If destructive operations are unavoidable, add a pre-migration backup or export step in the release checklist.

Document any non-trivial rollback considerations in the migration header comment or the release notes.

## Version identifiers & dependencies

Every migration must include an explicit header with:

- `Version`: the three-digit identifier that matches the filename prefix.
- `Depends on`: the immediate prerequisite migration(s).
- `Idempotent`: a short note describing how the script is safe to re-run.

Example header:

```sql
-- Migration: add address quality flags
-- Version: 003
-- Depends on: 002_address_validation_verdict
-- Idempotent: yes (IF NOT EXISTS)
```
