# Addressing smoke surface

This folder contains executable trust-surface probes for the current Addressing slice.

## Current intent

The current runtime is Doctrine-first for schema and repository authority, while a few native-connection helpers still exist. The smoke files are meant to answer one narrow question: do the entrypoints and service graph that the repository claims to expose actually exist in the current slice?

## Files

- `address-runtime-smoke.php` boots the runtime and checks the primary HTTP service, Doctrine-backed schema wiring, and the native connection surface.
- `address-fixture-sanity.php` checks the fixture and input/service graph.
- `address-container-boot-smoke.php` verifies the container can resolve Twig, forms, and the address HTTP service set.
- `address-fixture-load-smoke.php` performs a minimal fixture load against the current runtime wiring.
- `address-doctrine-mapping-smoke.php` must pass because Addressing now exposes Doctrine ORM mapping as the canonical schema authority.
- `address-graphql-smoke.php` is intentionally `not_applicable` because the current slice exposes no GraphQL surface.

## Notes

These scripts do not claim feature completeness. They only make the executable trust surface more honest.
