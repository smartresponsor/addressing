# Address outbox event contracts

The `address_outbox` table stores domain events emitted by the Address service. Each row includes an `event_name`, `event_version`, and JSON `payload`.

## Versioning

- `event_version` is an integer version for the payload shape. The current version for all address events is `1`.
- When a payload needs to change in a non-backward-compatible way (renamed/removed fields, type changes), increment `event_version` and document the new version.
- Backward-compatible changes should be additive (new optional fields) and must not rename or remove existing fields in the same version.

## Event payloads

### `AddressCreated` (v1)

Emitted after a new address row is inserted.

| Field | Type | Description |
| --- | --- | --- |
| `id` | string | ULID for the address record. |
| `ownerId` | string \| null | Owner identifier (nullable). |
| `vendorId` | string \| null | Vendor identifier (nullable). |
| `countryCode` | string | ISO 3166-1 alpha-2 country code. |
| `createdAt` | string | ISO-8601 timestamp (`DATE_ATOM`) of creation. |

### `AddressUpdated` (v1)

Emitted after an address row is updated.

| Field | Type | Description |
| --- | --- | --- |
| `id` | string | ULID for the address record. |
| `updatedAt` | string | ISO-8601 timestamp (`DATE_ATOM`) for the update. |

### `AddressDeleted` (v1)

Emitted after an address row is soft-deleted.

| Field | Type | Description |
| --- | --- | --- |
| `id` | string | ULID for the address record. |
| `deletedAt` | string | ISO-8601 timestamp (`DATE_ATOM`) for the delete operation. |

### `AddressValidatedApplied` (v1)

Emitted after validation data is applied to an address record.

| Field | Type | Description |
| --- | --- | --- |
| `id` | string | ULID for the address record. |
| `fingerprint` | string | Validation fingerprint used for idempotency. |
| `provider` | string | Validation provider name. |
| `validatedAt` | string | ISO-8601 timestamp (`DATE_ATOM`) when validation was applied. |
| `deliverable` | boolean \| null | Deliverability verdict, if provided. |
| `granularity` | string \| null | Granularity from the verdict (e.g., rooftop, street). |
| `quality` | integer \| null | Quality score from the verdict. |
| `rawSha256` | string \| null | SHA-256 of the raw validation payload, when raw payload is stored. |

