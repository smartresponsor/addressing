# Addressing entity-first migration retirement

## Scope

This pass retires the Addressing schema-first SQL authority and moves the remaining legacy address catalog concepts into PHP Doctrine entities.

## Retired schema-first sources

- `Addressing/sql/**`
- `Addressing/src/Projection/AddressIndex/schema.mysql.sql`
- `Addressing/src/Projection/AddressIndex/schema.sqlite.sql`

## Existing entity-first coverage kept

- `AddressEntity`
- `AddressEvidenceSnapshotEntity`
- `AddressIndexEntity`
- `AddressOutboxEntity`
- `RateLimitEntity`

Those classes were not duplicated.

## Restored concepts from old monolith `Entity/Address`

- `AddressCountryEntity`
- `AddressProvinceEntity`
- `AddressCityEntity`
- `AddressPostalCodeEntity`
- `AddressStreetTypeEntity`
- `AddressStreetEntity`
- `AddressFormatEntity`
- `AddressComponentEntity`

Old locale/country-specific format classes such as `AuAddressFormat`, `CaAddressFormat`, and `UaAddressFormat` are normalized into `AddressFormatEntity` rather than kept as separate country-specific Doctrine entities.

## Objecting rule

New restored entities use Objecting embeddable traits for system fields:

- `ObjectIdentityEmbeddableTrait`
- `ObjectAuditEmbeddableTrait`
- `ObjectStateEmbeddableTrait`

System identity/audit/state columns are not redefined ad-hoc in each restored Addressing entity.
