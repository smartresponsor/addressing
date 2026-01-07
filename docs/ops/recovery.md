# Recovery Runbook

## Purpose
Recover the service after database outage or data corruption.

## Critical ENV
- `PG_HOST`, `PG_PORT`, `PG_DATABASE`, `PG_USER`, `PG_PASSWORD`
- `MY_HOST`, `MY_PORT`, `MY_DATABASE`, `MY_USER`, `MY_PASSWORD`
- Optional: `OUTBOX_WEBHOOK_URL`

## Steps
1. Restore Postgres from the latest verified backup/snapshot.
2. Run schema migration to ensure the schema is up to date:
   ```bash
   php bin/address-migrate
   ```
3. Rebuild the MySQL projection:
   ```bash
   php bin/address-projection-runner
   ```
4. If outbox processing is required, drain the outbox:
   ```bash
   php bin/address-outbox-drain
   ```

## Success criteria
- Postgres is available and schema checks pass.
- Projection rebuild completes and MySQL tables are populated.
- Outbox drain completes without errors (if applicable).
- Read operations return expected data.
