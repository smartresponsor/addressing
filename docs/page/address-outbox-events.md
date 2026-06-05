# Address outbox event contracts

The `address_outbox` table stores domain events emitted by the Address service. Each row includes `event_name`, `event_version`, and JSON `payload` columns; the drainer then emits an envelope of the form:

```json
{
  "name": "AddressValidatedApplied",
  "version": 1,
  "payload": {
    "eventName": "AddressValidatedApplied",
    "schemaVersion": "address-outbox.v1",
    "eventVersion": 1,
    "occurredAt": "2025-01-15T12:31:11Z"
  }
}
```

## Versioning

- `event_version` is the transport version for the outbox row. The current version for all shipped Address events is `1`.
- The persisted payload is additionally decorated by `AddressOutboxEventMessage` with `schemaVersion`, `eventVersion`, and `occurredAt`.
- When a payload changes incompatibly, increment the event version and document the new shape. Additive fields are allowed within the same version.

## Registered events

Current event names registered by `App\Contract\Message\AddressOutboxEventMessage`:

- `AddressCreated`
- `AddressUpdated`
- `AddressDeleted`
- `AddressOperationalPatched`
- `AddressValidatedApplied`

## Event payloads

### `AddressCreated` (v1)

Emitted after a new address row is inserted.

| Field | Type | Description |
| --- | --- | --- |
| `eventName` | string | Decorated event name (`AddressCreated`). |
| `schemaVersion` | string | Outbox schema version (`address-outbox.v1`). |
| `eventVersion` | integer | Event payload version. |
| `occurredAt` | string | ISO-8601 timestamp when the payload was created. |
| `id` | string | ULID for the address record. |
| `ownerId` | string \| null | Owner identifier. |
| `vendorId` | string \| null | Vendor identifier. |
| `countryCode` | string | ISO 3166-1 alpha-2 country code. |
| `createdAt` | string | ISO-8601 creation timestamp. |

### `AddressUpdated` (v1)

Emitted after an address row is updated.

| Field | Type | Description |
| --- | --- | --- |
| `eventName` | string | Decorated event name. |
| `schemaVersion` | string | Outbox schema version. |
| `eventVersion` | integer | Event payload version. |
| `occurredAt` | string | Payload creation timestamp. |
| `id` | string | ULID for the address record. |
| `updatedAt` | string | ISO-8601 timestamp for the update. |

### `AddressDeleted` (v1)

Emitted after an address row is soft-deleted.

| Field | Type | Description |
| --- | --- | --- |
| `eventName` | string | Decorated event name. |
| `schemaVersion` | string | Outbox schema version. |
| `eventVersion` | integer | Event payload version. |
| `occurredAt` | string | Payload creation timestamp. |
| `id` | string | ULID for the address record. |
| `deletedAt` | string | ISO-8601 timestamp for the delete operation. |

### `AddressOperationalPatched` (v1)

Emitted after an operational patch mutates operational state on an address row.

| Field | Type | Description |
| --- | --- | --- |
| `eventName` | string | Decorated event name. |
| `schemaVersion` | string | Outbox schema version. |
| `eventVersion` | integer | Event payload version. |
| `occurredAt` | string | Payload creation timestamp. |
| `id` | string | ULID for the address record. |
| `updatedAt` | string | ISO-8601 timestamp of the patch write. |

### `AddressValidatedApplied` (v1)

Emitted after validation data is applied to an address record.

| Field | Type | Description |
| --- | --- | --- |
| `eventName` | string | Decorated event name. |
| `schemaVersion` | string | Outbox schema version. |
| `eventVersion` | integer | Event payload version. |
| `occurredAt` | string | Payload creation timestamp. |
| `id` | string | ULID for the address record. |
| `ownerId` | string \| null | Owner identifier. |
| `vendorId` | string \| null | Vendor identifier. |
| `fingerprint` | string | Validation fingerprint used for idempotency. |
| `validatedAt` | string | ISO-8601 timestamp when validation was applied. |
| `rawSha256` | string \| null | SHA-256 of the raw validation payload, when raw payload is stored. |
| `governanceStatus` | string | Governance status after apply. |
| `duplicateOfId` | string \| null | Canonical row if this row is a duplicate. |
| `supersededById` | string \| null | Successor row if this row was superseded. |
| `aliasOfId` | string \| null | Canonical row if this row is an alias. |
| `conflictWithId` | string \| null | Conflicting row if one exists. |
| `revalidationDueAt` | string \| null | Scheduled revalidation timestamp. |
| `revalidationPolicy` | string \| null | Revalidation policy. |
| `lastValidationStatus` | string | Latest validation status after apply. |
| `lastValidationScore` | integer \| null | Latest validation score after apply. |
| `evidenceSnapshotId` | string \| null | Companion evidence snapshot identifier, when created. |
| `providerDigest` | string \| null | Provider digest/provenance lineage value. |
