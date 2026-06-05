# Addressing W07 RC proof fix

This pass fixes failures found during local RC proof after W06.

## Fixed

- Removed missing `tests/Integration` directory from PHPUnit config.
- Fixed Deptrac path resolution when config is loaded from `config/addressing_deptrac.yaml`.
- Rebuilt `phpstan.neon` to load Symfony/Doctrine PHPStan extensions and Composer autoload.
- Removed stale repository-internal calls to removed `get()` helper after physical repository split.
- Fixed nullable-safe call warning in lifecycle compatibility test.

## Delete cleanup required after overlay

Remove stale local file if it exists from earlier W04 apply:

```text
src/Repository/Persistence/DoctrineAddressRepository.php
```

It is not part of the W07 canonical tree.
