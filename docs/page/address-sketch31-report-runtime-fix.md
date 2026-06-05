Address-sketch31-8-runtime-fix

Goal

- Fix namespaces and autoload for AddressRecord services and CLI scripts.

Included fixes

- Namespace normalized to App\Service\Address for AddressOutboxDrainer, AddressProjection, and AddressValidatedApplier.
- bin scripts now require vendor/autoload.php and use App\Entity\Address\AddressEntity and
  App\Service\Application\Address\AddressProjection.

Prerequisite

- Apply Address-sketch31-4-compile-repair first.

Out of scope

- HTTP API changes.
- Removing Engine utilities.
