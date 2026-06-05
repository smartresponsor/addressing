# Address projection contract

## Current authoritative runtime shape

The Address component no longer exposes two competing projection models.

Authoritative surfaces are:

- `address_entity` as the write-side source of truth
- `src/Projection/AddressIndex/*` as the live projection/read-model surface
- `address_index` as the surviving projection table contract

## What is intentionally not part of the current runtime

The repository no longer treats `address_projection` as a supported runtime table contract.
A prior MySQL-only projection path created drift because it:

- depended on `ON DUPLICATE KEY UPDATE`
- was not wired into the current runtime surface
- implied a second projection authority next to `AddressIndex`

## Host integration expectation

Host applications should treat projection support as an optional read-model capability rooted in `AddressIndex`.
They should not expect Doctrine metadata or a MySQL `address_projection` schema to be the projection authority for this component.
