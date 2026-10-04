# Addressing HTTP surface

## Current posture

The standalone runtime dispatches Addressing-owned business HTTP operations through Symfony Router metadata in typed controllers under `src/Controller/`; the controllers delegate to narrow Symfony HTTP services. `AddressApiController` owns manage/read/write/operational transport actions, while `AddressSummaryApiController` owns summary and governance read transport actions. `public/index.php` retains the pre-routing security/middleware boundary and hands the request to Symfony `HttpKernel`. There is no monolithic `AddressHttpService` in the current runtime.

Generic application CRUD grammar remains owned by Cruding. The services below are Addressing-specific transport/application boundaries for the current standalone API and operational workflows; they are not a reusable CRUD router or generic CRUD engine.

## Runtime dispatch

The typed controllers delegate to:

- `AddressApiController` → `AddressManageHttpService` for `GET|POST /address/manage`;
- `AddressApiController` → `AddressWriteHttpService` for address creation and soft deletion;
- `AddressApiController` → `AddressReadHttpService` for scoped reads, cursor paging, and search;
- `AddressSummaryApiController` → `AddressSummaryHttpService` for queue, portfolio, and governance summaries;
- `AddressApiController` → `AddressOperationalHttpService` for validation application and operational patches.

The JSON API paths are:

- `POST /api/address`;
- `GET /api/address/page`;
- `GET /api/address/search`;
- `GET /api/address/queue-summary`;
- `GET /api/address/country-portfolio`;
- `GET /api/address/source-portfolio`;
- `GET /api/address/validation-portfolio`;
- `GET /api/address/normalization-portfolio`;
- `POST /api/address/operational-batch`;
- `GET|PATCH|DELETE /api/address/{id}`;
- `POST /api/address/{id}/validated`;
- `GET /api/address/{id}/governance-cluster`.

## HTTP helpers

- `src/Factory/AddressQueryFilterFactory.php` builds scoped query, limit, country-code, operational, and portfolio filters.
- `src/Factory/AddressViewArrayFactory.php` builds response payload arrays and preview rows for `AddressInterface` records.
- `src/Factory/AddressApiPayloadFactory.php` decodes JSON requests and assembles address creation, validated-event, operational-patch, and string-id-list payloads.
- `src/Service/Http/Address/AddressHttpScopeService.php` resolves request ownership/vendor scope.
- `src/Responder/AddressResponder.php` owns shared JSON response shaping.

## Route-drift safeguard

`composer report:route-inventory` derives deterministic path/method evidence from the current Symfony `#[Route]` attributes across typed root controllers in `src/Controller/`. `composer qa:trust-surface` verifies that the report still proves the core HTTP methods, `/address/manage`, `/api/address`, and the dynamic route family.

The canonical machine-readable API description is `config/openapi/address_openapi.yaml`.
