# Migration Runbook

## Purpose
Apply Postgres schema and initialize MySQL projection for the Address data domain.

## Critical ENV
- `PG_HOST`, `PG_PORT`, `PG_DATABASE`, `PG_USER`, `PG_PASSWORD`
- `MY_HOST`, `MY_PORT`, `MY_DATABASE`, `MY_USER`, `MY_PASSWORD`
- Optional: `ADDRESS_DELETE_TTL_DAYS`, `OUTBOX_WEBHOOK_URL`

## Steps
1. Ensure environment variables are exported and point to the target databases.
2. Run the Postgres migration:
   ```bash
   php bin/address-migrate
   ```
3. Build the MySQL projection:
   ```bash
   php bin/address-projection-runner
   ```

## Success criteria
- Postgres schema objects created without errors.
- MySQL projection tables are present and populated.
- Optional: run a smoke check by reading a known address or querying table counts.
