# Addressing Canon/SOLID W04

Scope: physical Doctrine repository split after W03b removed the old composite repository interface.

## Changed

- Removed the monolithic `src/Repository/Persistence/DoctrineAddressRepository.php` implementation.
- Added shared Doctrine persistence base:
  - `src/Repository/Persistence/AbstractDoctrineAddressRepository.php`
- Added narrow Doctrine repository implementations:
  - `DoctrineAddressWriteRepository`
  - `DoctrineAddressReadRepository`
  - `DoctrineAddressEvidenceRepository`
  - `DoctrineAddressOperationalRepository`
  - `DoctrineAddressQueueRepository`
  - `DoctrineAddressGovernanceRepository`
  - `DoctrineAddressPortfolioRepository`
- Updated Symfony service aliases so each narrow repository interface resolves to its matching implementation.
- Updated repository presence/SOLID tests to validate the physical split.
- Kept no legacy facade and no old composite repository class.

## Validation performed

- PHP lint clean across all PHP files available in the snapshot.

## Not performed in this environment

- Composer autoload regeneration and PHPUnit execution were not available because Composer is not installed in the execution container.
