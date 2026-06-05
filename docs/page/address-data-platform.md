# Address data platform

## Overview
AddressEntity is the system of record for canonical address entities. It stores user-supplied address data, normalized snapshots, validation outcomes, governance links, revalidation state, and emits outbox events for downstream consumers. The public API surface is documented in `openapi/address.yaml`, while the persistence contract lives in:

- `sql/postgres/001_address.sql`
- `sql/postgres/002_address_validation_verdict.sql`
- `sql/postgres/002_address_entity_contract_sync.sql`
- `sql/postgres/003_address_outbox_runtime_sync.sql`

Together with `src/Integration/Persistence/AddressSchemaManager.php`, these files define the runtime schema contract from HTTP request through database storage, evidence capture, and event emission.

## Business value
- **Single source of truth:** a consistent address record for every owner/vendor pair, with dedupe, validation, governance, and revalidation metadata.
- **Operational reliability:** append-only outbox events provide an auditable trail and deterministic integration with downstream services.
- **Traceable validation:** normalized fields, provider verdicts, provider digests, and raw provider payloads keep validation decisions explainable and reproducible.
- **Runtime clarity:** the host application receives Address schema authority from the persistence layer, not from Doctrine metadata inference.

## Core data objects

### Address record (`address_entity`)
The canonical address record stores both CRUD data and operational state.

Key field groups:
- **Identity:** `id`, `owner_id`, `vendor_id`
- **Address payload:** `line1`, `line2`, `city`, `region`, `postal_code`, `country_code`
- **Normalized fields:** `line1_norm`, `city_norm`, `region_norm`, `postal_code_norm`, `dedupe_key`
- **Geo fields:** `latitude`, `longitude`, `geohash`
- **Validation state:** `validation_status`, `validation_provider`, `validated_at`, `validation_fingerprint`, `validation_*`
- **Provenance:** `source_system`, `source_type`, `source_reference`, `normalization_version`, `raw_input_snapshot`, `normalized_snapshot`, `provider_digest`
- **Governance state:** `governance_status`, `duplicate_of_id`, `superseded_by_id`, `alias_of_id`, `conflict_with_id`
- **Revalidation state:** `revalidation_due_at`, `revalidation_policy`, `last_validation_provider`, `last_validation_status`, `last_validation_score`
- **Lifecycle timestamps:** `created_at`, `updated_at`, `deleted_at`

### Evidence history (`address_evidence_snapshot`)
Validated apply operations can emit an evidence snapshot that preserves:
- raw input snapshot
- normalized snapshot
- validation status / score / issues
- provider digest
- validated-by / validated-at lineage

This is the audit/provenance companion surface to `address_entity`, not a replacement for the canonical address row.

### Outbox event (`address_outbox`)
Every write emits a durable outbox row with:
- `event_name`
- `event_version`
- `payload`
- `created_at`
- `published_at`
- `published_attempt`
- `last_error`
- lock metadata used by the drainer

The outbox table is part of the runtime contract and is synchronized across SQLite and Postgres surfaces.

## Invariants
- **ULID identifiers:** `id` uses a 26-character ULID.
- **Country code length:** `country_code` must be exactly 2 characters.
- **Validation status guardrail:** `validation_status` is constrained to `unknown`, `pending`, `normalized`, `validated`, `rejected`, `uncertain`, `overridden`.
- **Governance status guardrail:** `governance_status` is constrained to `canonical`, `duplicate`, `superseded`, `alias`, `conflict`.
- **Dedupe uniqueness:** `dedupe_key` is unique when present; a trigger can derive it from normalized fields.
- **Soft delete:** `deleted_at` marks logical deletion; active-query surfaces exclude deleted rows.

## Event guarantees
- **Durable write path:** writes to `address_entity` append a corresponding `address_outbox` row in the same write cycle.
- **Ordered delivery:** the outbox drainer processes unpublished rows in ascending `id` order.
- **At-least-once delivery:** failed deliveries increment `published_attempt` and persist `last_error`; successful deliveries set `published_at`.
- **Stable envelope:** drainer output is `{ name, version, payload }`, while the persisted payload itself is decorated with `eventName`, `schemaVersion`, `eventVersion`, and `occurredAt`.

## Sample address record
Example response-aligned record shape for the current Address slice:

```json
{
  "id": "01J3XZ9S1Q2H6R8J9K3M5V7T9W",
  "ownerId": "owner-123",
  "vendorId": "vendor-456",
  "line1": "10 Downing St",
  "line2": "Apt 2",
  "city": "London",
  "region": "Greater London",
  "postalCode": "SW1A 2AA",
  "countryCode": "GB",
  "line1Norm": "10 downing st",
  "cityNorm": "london",
  "regionNorm": "greater london",
  "postalCodeNorm": "sw1a2aa",
  "latitude": 51.5033,
  "longitude": -0.1276,
  "geohash": "gcpvj0d1j",
  "validationStatus": "validated",
  "validationProvider": "nominatim",
  "governanceStatus": "canonical",
  "revalidationPolicy": "quarterly",
  "lastValidationStatus": "validated",
  "createdAt": "2025-01-15T12:30:00Z",
  "validatedAt": "2025-01-15T12:31:10Z"
}
```

## Sample validated-apply outbox payload
Example payload stored inside `address_outbox.payload` for a validated apply:

```json
{
  "eventName": "AddressValidatedApplied",
  "schemaVersion": "address-outbox.v1",
  "eventVersion": 1,
  "occurredAt": "2025-01-15T12:31:11Z",
  "id": "01J3XZ9S1Q2H6R8J9K3M5V7T9W",
  "ownerId": "owner-123",
  "vendorId": "vendor-456",
  "fingerprint": "e3f4f1a3d5d9c7e5f2a0b7812d8b4d9d2b1b8f84f4f7b9b7a67f8895e21a6b2e",
  "validatedAt": "2025-01-15T12:31:10Z",
  "rawSha256": "4f2c9b4bcd12c0fbf4ad0f8b5a204e2cda6f36a9d4c7ed6bb7e4cfb6c5e5c2a7",
  "governanceStatus": "canonical",
  "lastValidationStatus": "validated",
  "lastValidationScore": 98,
  "evidenceSnapshotId": "01J3XZB6E4A8K4M3Y6N2T1R5Q8",
  "providerDigest": "sha256:abc123"
}
```

## End-to-end traceability
- **API contract → Address record:** request/response surfaces in `openapi/address.yaml` map to `address_entity` columns.
- **Validation contract → Stored state:** `App\Contract\Message\AddressValidated` and `AddressValidatedMutationPlan*` populate `validation_*`, governance, provenance, and revalidation columns.
- **Validated apply → Evidence history:** `AddressEvidenceSnapshotWriter` captures the validation/evidence companion row.
- **Write path → Event emission:** CRUD and validation updates append payloads into `address_outbox`, and `AddressOutboxDrainerService` emits `{ name, version, payload }` envelopes.
