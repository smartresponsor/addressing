# Recovery runbook

## Purpose
Recover Address DataDomain after infrastructure or data incidents.

## Scenarios
- Database crash or data corruption
- Projection out of sync with Postgres
- Outbox processing backlog

## Steps
1) Restore Postgres to the last consistent snapshot.
2) Apply migrations if needed:
   ```bash
   php bin/address-migrate
   ```
3) Restore MySQL projection snapshot (or re-create it):
   ```bash
   php bin/address-projection-migrate
   ```
4) Rebuild projection from Postgres:
   ```bash
   php bin/address-projection-runner
   ```
5) Resume outbox draining:
   ```bash
   php bin/address-outbox-drain
   ```

## Verification
- Validate that projection timestamps align with Postgres.
- Validate outbox drain completes without errors.
