# Addressing Doctrine read-surface transition

This wave moves the primary read/reporting surface away from the legacy PDO repository and into `DoctrineAddressRepository`.

## Moved to Doctrine/DBAL-backed primary path

- paged address search (`findPage`)
- governance cluster summary (`summarizeGovernanceCluster`)
- operational queue summary (`summarizeOperationalQueues`)
- country portfolio summary (`summarizeCountryPortfolio`)
- source portfolio summary (`summarizeSourcePortfolio`)
- validation portfolio summary (`summarizeValidationPortfolio`)
- normalization portfolio summary (`summarizeNormalizationPortfolio`)

## Legacy fallback still remaining

Only `patchOperational()` still delegates to the legacy PDO repository.

## Practical meaning

`AddressRepositoryInterface` is now Doctrine-first not only for create/update/get/delete but also for the principal read/reporting surfaces used by host-facing operational views.
