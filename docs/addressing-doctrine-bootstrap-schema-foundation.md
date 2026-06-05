# Addressing Doctrine bootstrap/schema foundation

Addressing no longer relies on `AddressPdoFactory` or `AddressSchemaManager` as the primary bootstrap/schema authority.

## Canonical bootstrap/schema authority

- Doctrine ORM entity metadata under `src/Entity`
- `App\Doctrine\AddressDoctrineSchemaManager`
- Symfony host DBAL connection exposed through `doctrine.dbal.default_connection`

## Transitional note

A native `PDO` service is still exposed for helper flows that have not yet been rewritten away from direct SQL, but it is now obtained from Doctrine DBAL rather than from a component-owned PDO factory.
