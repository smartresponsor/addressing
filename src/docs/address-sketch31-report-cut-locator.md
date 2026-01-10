Address-sketch31-6-cut-locator

Goal

- Remove Locator responsibilities from Address repo surface area: no network geocode/validate/parse endpoints.

Changes

- Removed /api/address/validate, /api/address/parse, /api/address/geocode routes from public entry.
- Controller now exposes only read-model search endpoint.
- OpenAPI spec updated to Address Data API (index search only).

Tool (must apply deletions)

- src/Integration/Geocode/**
- tests/Geocode/**

Next

- Add AddressValidated contract + apply endpoint to accept validated payload from Locator.
