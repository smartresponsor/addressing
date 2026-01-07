# Rollback Runbook

## Purpose
Rollback a failed deployment by reverting application code and restoring the database state.

## Critical ENV
- `PG_HOST`, `PG_PORT`, `PG_DATABASE`, `PG_USER`, `PG_PASSWORD`
- `MY_HOST`, `MY_PORT`, `MY_DATABASE`, `MY_USER`, `MY_PASSWORD`

## Steps
1. Stop traffic to the service (or switch to maintenance mode).
2. Revert application code to the previous release artifact.
3. Restore Postgres from the last known good backup/snapshot.
4. Rebuild the MySQL projection to align with restored Postgres:
   ```bash
   php bin/address-projection-runner
   ```

## Success criteria
- Application is running on the previous release.
- Postgres data matches the restored backup.
- Projection rebuild completes without errors.
- Health checks and critical reads succeed.
