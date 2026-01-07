# Rollback runbook

## Purpose
Rollback Address DataDomain schema changes safely.

## Preconditions
- You have a verified backup/snapshot for both Postgres and MySQL.
- You have identified the migration window and affected tables.

## Steps
1) Stop writers to Address DataDomain (pause ingestion).
2) Restore Postgres from the last known good snapshot.
3) Restore MySQL projection from the last known good snapshot.
4) Re-run projection sync to ensure derived tables are consistent:
   ```bash
   php bin/address-projection-runner
   ```

## Verification
- Validate row counts and sample records.
- Confirm projection sync completes without errors.

## Notes
Rollback is destructive. Always coordinate with consumers before restoring snapshots.
