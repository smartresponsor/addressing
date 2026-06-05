# Addressing W05 canon/SOLID service split

## Scope

W05 removes the remaining application and HTTP god-service surfaces left after the repository split.

## Removed

- `src/Service/Application/AddressService.php`
- `src/Service/Http/Address/AddressHttpService.php`
- `tests/Unit/AddressServiceUnitTest.php`
- `tests/Functional/AddressHttpServiceFunctionalTest.php`

## Added application use-case services

- `src/Service/Application/AddressWriteService.php`
- `src/Service/Application/AddressReadService.php`
- `src/Service/Application/AddressEvidenceService.php`
- `src/Service/Application/AddressOperationalService.php`
- `src/Service/Application/AddressQueueSummaryService.php`
- `src/Service/Application/AddressGovernanceSummaryService.php`
- `src/Service/Application/AddressPortfolioSummaryService.php`

## Added HTTP surface services

- `src/Service/Http/Address/AddressManageHttpService.php`
- `src/Service/Http/Address/AddressWriteHttpService.php`
- `src/Service/Http/Address/AddressReadHttpService.php`
- `src/Service/Http/Address/AddressSummaryHttpService.php`
- `src/Service/Http/Address/AddressOperationalHttpService.php`
- `src/Service/Http/Address/AddressHttpScopeService.php`
- `src/Service/Http/Address/AddressHttpResponderService.php`

## Updated

- `public/index.php` now resolves dedicated HTTP services instead of one catch-all `AddressHttpService`.
- `config/addressing_services.yaml` exposes the concrete public HTTP entry services.
- Symfony commands and fixtures now depend on narrow application services.
- Smoke/inspection tools now verify the new narrow services.
- Functional test renamed to `AddressHttpSurfaceFunctionalTest`.
- Unit test renamed to `AddressReadServiceUnitTest`.
- Legacy bin scripts `address-create`, `address-search`, and `address-get` now delegate to Symfony console commands.

## Verification

- PHP lint clean across 175 PHP files.
- Empty folders: none.
- Cache artifacts: none.
- No live code references to the removed `AddressService` or `AddressHttpService` remain.

Composer/phpunit/phpstan/deptrac were not executed here because Composer is not installed in the execution container.
