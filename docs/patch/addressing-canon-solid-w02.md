# Addressing canon/SOLID cleanup W02

Scope: incremental pass after W01. This pass does not split `DoctrineAddressRepository`; it removes folder-level drift and normalizes Symfony-oriented placement before a later repository SRP/ISP split.

## Changed

- Moved Symfony console commands from `src/Integration/Console/Command` to `src/Command`.
- Moved demo fixture service from `src/Fixture` to `src/Service/Fixture`.
- Consolidated Doctrine support from split `src/Bridge/Doctrine` and `src/Infrastructure/Doctrine` into `src/Doctrine`.
- Renamed `AddressOutboxEventContract` to `AddressOutboxEventMessage` to remove misleading class suffix.
- Updated references in runtime code, bin scripts, tests, inspection tools, docs, and psalm baseline.
- Rebuilt `config/addressing_deptrac.yaml` so current folders are covered by explicit layers.

## Removed paths after move

- `src/Integration/Console/Command/`
- `src/Fixture/`
- `src/Bridge/Doctrine/`
- `src/Infrastructure/Doctrine/`
- `src/Contract/Message/AddressOutboxEventContract.php`

## Added canonical paths

- `src/Command/`
- `src/Service/Fixture/AddressDemoFixtureService.php`
- `src/Doctrine/AddressEntityMapper.php`
- `src/Doctrine/AddressDoctrineSchemaManager.php`
- `src/Contract/Message/AddressOutboxEventMessage.php`

## Verification

- PHP lint passed for `src`, `tests`, `public`, and `tools` PHP files.
- No empty directories remain in the snapshot.
- No references remain to old namespaces/paths:
  - `App\Bridge`
  - `App\Infrastructure`
  - `App\Fixture`
  - `App\Integration\Console\Command`
  - `AddressOutboxEventContract`
  - `src/Bridge`
  - `src/Infrastructure`
  - `src/Fixture`
  - `src/Integration/Console`

## Deferred

- `DoctrineAddressRepository` SRP/ISP split.
- `AddressService` application facade split.
- Repository interface segmentation.
