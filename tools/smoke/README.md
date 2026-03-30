# Addressing smoke surface

This folder contains executable trust-surface probes for the current Addressing slice.

## Current intent

The current runtime is PDO-first and Symfony-oriented. The smoke files are meant to answer one narrow question: do the entrypoints and service graph that the repository claims to expose actually exist in the current slice?

## Files

- `category-runtime-smoke.php` boots the runtime and checks the primary controller and PDO service.
- `category-fixture-sanity.php` checks the fixture and input/service graph.
- `category-container-boot-smoke.php` verifies the container can resolve Twig, forms, and the address controller.
- `category-fixture-load-smoke.php` performs a minimal fixture load against the current runtime wiring.
- `category-doctrine-mapping-smoke.php` is intentionally `not_applicable` because the current runtime is PDO-first and does not expose Doctrine ORM mapping in the Symfony container.
- `category-graphql-smoke.php` is intentionally `not_applicable` because the current slice exposes no GraphQL surface.

## Notes

These scripts do not claim feature completeness. They only make the executable trust surface more honest.
