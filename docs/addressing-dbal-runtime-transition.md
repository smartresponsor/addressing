# Addressing DBAL Runtime Transition

This wave moves the remaining core runtime mutation and outbox services away from native PDO and onto Doctrine DBAL.

## Scope

The following runtime surfaces now depend on `Doctrine\DBAL\Connection` instead of `PDO`:

- `AddressValidatedApplierService`
- `AddressValidatedMutationPlanBuilder`
- `AddressEvidenceSnapshotWriter`
- `AddressOutboxWriter`
- `AddressOutboxDrainerService`

## What changed

- transaction control now goes through DBAL
- row reads/writes use DBAL fetch/execute APIs
- PostgreSQL-specific JSON handling remains explicit where required
- SQLite test coverage for validated apply and outbox drain was updated to build DBAL connections directly

## Transitional note

This wave does **not** remove every direct SQL helper or every CLI script that still creates raw PDO connections. It removes PDO from the core runtime mutation/outbox path so the remaining direct-PDO surface is no longer authoritative.
