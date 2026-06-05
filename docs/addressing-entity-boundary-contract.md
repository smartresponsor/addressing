# Address entity boundary contract

## Summary

`AddressData` remains the canonical persistence-backed record for the component runtime, but the entity boundary is now explicitly decomposed into three responsibility clusters:

- `AddressValidationState`
- `AddressGovernanceState`
- `AddressRevalidationState`

This change does **not** introduce new tables and does **not** change the runtime schema authority. It makes the entity boundary explicit without breaking the current PDO-first persistence contract.

## Runtime authority

The host application still builds and upgrades schema through:

- `App\Integration\Persistence\AddressPdoFactory`
- `App\Integration\Persistence\AddressSchemaManager`
- `sql/postgres/*.sql`

It does **not** build schema from Doctrine entity metadata.

## Cluster boundaries

### AddressValidationState

Validation and provenance responsibility only:

- validation status/provider/time
- validation fingerprint/raw/verdict
- validation deliverable/granularity/quality
- source system/type/reference
- normalization version
- raw input snapshot
- normalized snapshot
- provider digest

### AddressGovernanceState

Canonical/governance relationship responsibility only:

- governance status
- duplicate/superseded/alias/conflict links

### AddressRevalidationState

Operational follow-up responsibility only:

- revalidation due date
- revalidation policy
- last validation provider/status/score

## Why this matters

Before this wave, these responsibilities were present only as field clusters inside `AddressData`. That made the record functionally rich but boundary-wise overloaded.

After this wave:

- the persistence model stays stable
- the schema stays stable
- the host integration stays stable
- the entity boundary becomes explicit and machine-readable in code
