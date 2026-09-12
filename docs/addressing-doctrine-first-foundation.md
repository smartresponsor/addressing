# Addressing Doctrine-first foundation

Addressing now exposes a Doctrine ORM authority layer for Symfony host applications.

## Canonical ORM authority

The new Doctrine authority classes are:

- `App\Addressing\Entity\AddressEntity`
- `App\Addressing\Entity\AddressEvidenceSnapshotEntity`

These classes are the intended host-facing schema authority for:

- Doctrine metadata discovery
- `doctrine:schema:validate`
- `doctrine:schema:update`
- future Doctrine migrations or schema diff flows in the hosted application

## Transitional runtime note

The legacy PDO-first runtime remains present during migration.

Current write/read runtime still relies on custom persistence services and record classes under `App\Addressing\Entity\Record\*`.
That runtime is now considered transitional and should converge toward Doctrine-backed repositories in subsequent waves.

## Host integration

Import the provided Doctrine mapping file:

- `config/packages/addressing_doctrine.yaml`

The host application should treat the top-level `App\Addressing\Entity\*` classes from this component as the schema authority, not the `Record` namespace classes.
