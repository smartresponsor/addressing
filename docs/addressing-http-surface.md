# Addressing HTTP surface notes

## Current posture

The HTTP layer remains route-stable while the controller becomes thinner and more Symfony-oriented.

## What now lives outside the controller

- `src/Http/Factory/AddressQueryFilterFactory.php` builds tenant extraction, limit handling, country-code normalization, and operational or portfolio filter arrays from the request query string.
- `src/Http/Factory/AddressViewArrayFactory.php` builds response payload arrays and preview rows for `AddressInterface` records.
- `src/Http/Factory/AddressApiPayloadFactory.php` decodes JSON requests and assembles API payload structures such as `AddressData`, `AddressValidated`, operational patches, and validated string-id lists.

## Why this matters

This keeps `src/Http/Controller/AddressController.php` focused on request orchestration, response status handling, and delegation to the application service layer, instead of mixing transport parsing, payload assembly, and response shaping directly into the controller body.

## Intended next step

A later wave can split the controller into narrower controllers or handlers with lower risk, because reusable HTTP query, payload, and view assembly logic is no longer trapped inside one large class.
