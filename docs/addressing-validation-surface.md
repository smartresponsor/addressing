# Addressing validation application surface notes

## Current posture

`src/Service/Application/AddressValidatedApplierService.php` remains the public application entrypoint for applying validated address payloads, but it no longer has to carry all validated-payload semantics, tenant-scope SQL assembly, and mutation-plan assembly internally.

## What now lives outside the applier service

- `src/Service/Application/AddressValidatedPayloadFactory.php` owns reusable validated-payload semantics such as evidence detection, normalized snapshot derivation, provider-digest derivation, governance-link normalization, and outbox payload shaping.
- `src/Integration/Persistence/AddressTenantScopeSqlHelper.php` owns tenant-scope SQL clause and parameter assembly.
- `src/Integration/Persistence/AddressValidatedMutationPlanBuilder.php` owns validated update-assignment and parameter-plan construction for the `address_entity` mutation path.
- `src/Integration/Persistence/AddressValidatedMutationPlan.php` carries the assembled mutation state used by the applier transaction.

## Why this matters

This keeps the applier service focused on orchestration of the validation apply transaction instead of mixing transaction flow with payload-shaping, tenant-scope helper logic, and large inline SQL update-plan assembly in one class body.

## Intended next step

A later wave can move more of the remaining persistence-heavy evidence-snapshot or outbox write details behind narrower collaborators with lower risk, because the biggest inline update-plan block is now outside the applier service.
