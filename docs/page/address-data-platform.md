# Address data platform

## Overview
AddressData is the system of record for canonical address entities. It stores CRUD data, normalized fields,
validation outcomes, and emits outbox events for downstream consumers. The public API surface is documented in
`openapi/address.yaml`, while the persistent schema lives in `sql/postgres/001_address.sql` and
`sql/postgres/002_address_validation_verdict.sql`. Together they define the end-to-end contract from HTTP request
through database storage and event emission.

## Business value
- **Single source of truth:** A consistent address record for every owner/vendor pair, with dedupe and validation
  metadata to eliminate drift across systems.
- **Operational reliability:** Append-only outbox events provide an auditable trail and deterministic integration
  with downstream services.
- **Traceable validation:** Normalized fields, provider verdicts, and raw provider payloads keep validation
  decisions explainable and reproducible.
- **Search-ready data:** Indexed columns and normalized fields support efficient filtering and future projections.

## Core data objects

### Address record (address_entity)
The canonical address record stores both user-entered fields and normalized/validated fields.
Key fields mapped in the API response include:
- **Identity:** `id` (ULID), `ownerId`, `vendorId`.
- **Location:** `line1`, `line2`, `city`, `region`, `postalCode`, `country`.
- **Normalized fields:** `line1Norm`, `cityNorm`, `regionNorm`, `postalCodeNorm`.
- **Geospatial:** `latitude`, `longitude`, `geohash`.
- **Validation status:** `status` (mapped from `validation_status`), `provider` (`validation_provider`),
  `validatedAt`.
- **Lifecycle timestamps:** `createdAt`, `updatedAt`, `deletedAt`.

These fields are stored in `address_entity` and projected through the API response defined in
`openapi/address.yaml`.

### Validation verdict fields
Validation provider payloads and verdict signals are stored alongside the address record to support audit and
analysis:
- `validation_raw` (provider payload, JSONB)
- `validation_verdict` (normalized verdict, JSONB)
- `validation_deliverable`, `validation_granularity`, `validation_quality`

These are defined in `sql/postgres/002_address_validation_verdict.sql` and populated from the
`App\Contract\Address\AddressValidated` contract.

### Outbox event (address_outbox)
Every write emits a durable outbox row with:
- `event_name` (e.g., `AddressCreated`, `AddressUpdated`, `AddressDeleted`, `AddressValidatedApplied`)
- `event_version` (currently `1`)
- `payload` (JSONB payload per event type)
- `created_at`, `published_at`, `published_attempt`, `last_error`

The outbox schema and delivery metadata live in `sql/postgres/001_address.sql`.

## Invariants
- **ULID identifiers:** `id` uses a 26-character ULID; API path parameters enforce this.
- **Country code length:** `country_code` must be exactly 2 characters.
- **Validation status guardrail:** `validation_status` is constrained to `unknown`, `normalized`, or `validated`.
- **Dedupe uniqueness:** `dedupe_key` is unique when present; a trigger can derive it from normalized fields.
- **Soft delete:** `deleted_at` marks logical deletion; queries for active records exclude deleted rows.

## Event guarantees
- **Durable write path:** Writes to `address_entity` append a corresponding `address_outbox` row in the same
  transaction, so events are never emitted without persisted data.
- **Ordered delivery:** The outbox drainer emits events in ascending `id` order (first-in, first-out per table).
- **At-least-once delivery:** Failed deliveries are retried and tracked with `published_attempt` and `last_error`;
  successful deliveries set `published_at`.
- **Stable versioning:** Event payloads include `event_version = 1`, enabling forward-compatible evolution.

## Sample address record
Example response body aligned with `AddressResponse` in `openapi/address.yaml`:

```json
{
  "id": "01J3XZ9S1Q2H6R8J9K3M5V7T9W",
  "ownerId": "owner-123",
  "vendorId": "vendor-456",
  "tag": "shipping",
  "name": "Alex Doe",
  "company": "Example Co",
  "phone": "+1-415-555-0100",
  "email": "alex@example.com",
  "line1": "10 Downing St",
  "line2": "Apt 2",
  "city": "London",
  "region": "Greater London",
  "postalCode": "SW1A 2AA",
  "country": "GB",
  "line1Norm": "10 downing st",
  "cityNorm": "london",
  "regionNorm": "greater london",
  "postalCodeNorm": "sw1a2aa",
  "latitude": 51.5033,
  "longitude": -0.1276,
  "geohash": "gcpvj0d1j",
  "status": "validated",
  "provider": "nominatim",
  "createdAt": "2025-01-15T12:30:00Z",
  "updatedAt": "2025-01-15T12:30:00Z",
  "validatedAt": "2025-01-15T12:31:10Z",
  "deletedAt": null
}
```

## Sample outbox event payload
Example event delivery envelope emitted by the outbox drainer for a validated apply, aligned with
`App\Contract\Address\AddressValidated` and the outbox row payload:

```json
{
  "name": "AddressValidatedApplied",
  "version": 1,
  "payload": {
    "id": "01J3XZ9S1Q2H6R8J9K3M5V7T9W",
    "fingerprint": "e3f4f1a3d5d9c7e5f2a0b7812d8b4d9d2b1b8f84f4f7b9b7a67f8895e21a6b2e",
    "provider": "nominatim",
    "validatedAt": "2025-01-15T12:31:10Z",
    "deliverable": true,
    "granularity": "premise",
    "quality": 98,
    "rawSha256": "4f2c9b4bcd12c0fbf4ad0f8b5a204e2cda6f36a9d4c7ed6bb7e4cfb6c5e5c2a7"
  }
}
```

## End-to-end traceability
- **API contract → Address record:** `AddressCreateRequest` and `AddressResponse` in `openapi/address.yaml`
  map to `address_entity` columns defined in `sql/postgres/001_address.sql`.
- **Validation contract → Stored verdict:** `App\Contract\Address\AddressValidated` populates
  `validation_*` columns defined in `sql/postgres/002_address_validation_verdict.sql`.
- **Write path → Event emission:** CRUD and validation updates append JSON payloads into `address_outbox`
  (`sql/postgres/001_address.sql`), which are delivered by the outbox drainer as `{ name, version, payload }`.
