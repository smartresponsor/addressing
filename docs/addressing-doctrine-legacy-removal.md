# Addressing Doctrine legacy removal

The production repository path is now Doctrine-only. `DoctrineAddressRepository` no longer depends on the legacy PDO repository for operational patching or read/reporting surfaces.

Current state:
- primary repository authority: `App\Repository\Persistence\DoctrineAddressRepository`
- host-facing schema authority: Doctrine ORM entities under `src/Entity/`
- removed from service wiring: `App\Repository\Persistence\AddressRepository`

Important:
- `AddressPdoFactory` and `AddressSchemaManager` have been removed from the primary bootstrap path. Schema reset/bootstrap now flows through `AddressDoctrineSchemaManager` and Doctrine ORM metadata.
- the old PDO repository file can be physically deleted from the consumer repository after overlay apply.
