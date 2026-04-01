# Addressing validation application surface notes

## Current posture

`src/Service/Application/AddressValidatedApplierService.php` remains the public application entrypoint for applying validated address payloads, but it no longer has to carry all validated-payload semantics and tenant-scope SQL assembly internally.

## What now lives outside the applier service

- `src/Service/Application/AddressValidatedPayloadFactory.php` owns reusable validated-payload semantics such as evidence detection, normalized snapshot derivation, provider-digest derivation, governance-link normalization, and outbox payload shaping.
- `src/Integration/Persistence/AddressTenantScopeSqlHelper.php` owns tenant-scope SQL clause and parameter assembly.

## Why this matters

This keeps the applier service focused on orchestration of the validation apply transaction instead of mixing transaction flow with payload-shaping and tenant-scope helper logic in one class body.

## Intended next step

A later wave can move more of the persistence-heavy SQL update assembly behind narrower persistence collaborators with lower risk, because the applier service now has less non-transactional helper logic embedded inline.
