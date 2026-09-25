# Addressing HTTP surface

## Current posture

The standalone runtime currently dispatches Addressing-owned business HTTP operations from `public/index.php` to narrow Symfony HTTP services. There is no monolithic `AddressHttpService` in the current runtime.

Generic application CRUD grammar remains owned by Cruding. The services below are Addressing-specific transport/application boundaries for the current standalone API and operational workflows; they are not a reusable CRUD router or generic CRUD engine.

## Runtime dispatch

`public/index.php` delegates to:

- `AddressManageHttpService` for `GET|POST /address/manage`;
- `AddressWriteHttpService` for address creation and soft deletion;
- `AddressReadHttpService` for scoped reads, cursor paging, and search;
- `AddressSummaryHttpService` for queue, portfolio, and governance summaries;
- `AddressOperationalHttpService` for validation application and operational patches.

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

`composer report:route-inventory` derives exact and regex-backed route evidence from the current front controller. `composer qa:trust-surface` verifies that the report still proves the core HTTP methods, `/address/manage`, `/api/address`, and the dynamic route family.

The canonical machine-readable API description is `openapi/address.yaml`.
