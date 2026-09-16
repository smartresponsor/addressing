# Address entity boundary contract

## Summary

`AddressData` is the immutable Addressing record used across the application/read boundary. Persistence authority belongs to the Doctrine entities and their attribute mappings, while `AddressEntityMapper` translates between the record and the primary Doctrine `AddressEntity`.

The record boundary is explicitly decomposed into three responsibility clusters:

- `AddressValidationState`
- `AddressGovernanceState`
- `AddressRevalidationState`

This decomposition does **not** create separate persistence tables for those state clusters. It keeps the application-facing record explicit while Doctrine remains the schema/runtime persistence authority.

## Runtime authority

The current persistence path is Doctrine-first:

- `App\Addressing\Entity\AddressEntity` owns the primary address mapping;
- `App\Addressing\Entity\AddressEvidenceSnapshotEntity` owns validation/evidence snapshots;
- `App\Addressing\Entity\AddressOutboxEntity` owns the local outbox mapping;
- `App\Addressing\Doctrine\AddressEntityMapper` translates `AddressData` to and from `AddressEntity`;
- `App\Addressing\Doctrine\AddressDoctrineSchemaManager` uses Doctrine `SchemaTool` for local/demo schema ensure/reset flows;
- `config/packages/addressing_doctrine.yaml` binds the Addressing mapping to the configured `infra` Doctrine connection/entity manager.

The retired PDO factory/schema-manager topology is not a current runtime contract. Host applications consume Addressing through its Symfony bundle/package and Doctrine mappings rather than through component-owned raw SQL schema authority.

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

In the current runtime:

- Doctrine entity metadata is the executable persistence model;
- `AddressData` remains a transport-safe immutable record rather than the schema authority;
- validation, governance, and revalidation state remain explicit and machine-readable;
- schema reset/ensure behavior is centralized in the Doctrine schema manager instead of a parallel PDO schema path.
