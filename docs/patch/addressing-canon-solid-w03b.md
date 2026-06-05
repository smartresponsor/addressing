# Addressing W03b Canon SOLID Repository Contracts

This patch removes the legacy composite `AddressRepositoryInterface` facade.

## Changes

- Deleted `src/RepositoryInterface/Persistence/AddressRepositoryInterface.php`.
- `DoctrineAddressRepository` now implements the narrow repository contracts directly.
- Removed the Symfony alias for the deleted composite contract.
- `AddressService` now requires all narrow repository ports explicitly.
- Unit and repository tests were updated to assert narrow-port wiring only.

## Canon rule

No compatibility facade remains. Consumers must depend on the smallest required persistence port.
