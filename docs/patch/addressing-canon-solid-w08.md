# Addressing canon solid W08

RC proof fix pass after W07 local proof.

Fixes:

- Deptrac collector type updated from unsupported `className` to supported `classNameRegex`.
- PHPStan Symfony/Doctrine loaders now use `require_once` bootstrap includes to avoid `AddressRuntimeBootstrap` redeclaration in parallel workers.
- Functional test runtime now sets an isolated `APP_VAR_DIR` per sqlite runtime, preventing stale Symfony test cache from hiding newly public HTTP services.
- W08 proof script clears Symfony cache and runs composer/DI/YAML/PHPUnit/PHPStan/Deptrac checks.

Composer lock note:

- If `composer validate --strict` reports lock drift, run `composer update --lock` once locally and commit the resulting lock hash.
