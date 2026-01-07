# Migration runbook

## Purpose
Apply Postgres schema and MySQL projection schema for Address DataDomain.

## Preconditions
- Environment variables for Postgres and MySQL are set (`PG_*`, `MY_*`).
- Database users have DDL privileges.

## Steps
1) Apply Postgres data schema:
   ```bash
   php bin/address-migrate
   ```
2) Apply MySQL projection schema:
   ```bash
   php bin/address-projection-migrate
   ```
3) (Optional) Run projection sync if you have data to project:
   ```bash
   php bin/address-projection-runner
   ```

## Verification
- Ensure Postgres tables exist in the target DB.
- Ensure MySQL projection tables exist and are accessible.

## Rollback
If migration needs to be reverted, follow the rollback runbook.
