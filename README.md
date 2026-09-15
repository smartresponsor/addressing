# Addressing

Addressing is the Symfony address-lifecycle component of the Smart Responsor platform. It owns address records, normalization and validation evidence, operational state, governance links, scoped search, portfolio summaries, and address-specific HTTP/CLI operations.

Addressing does **not** own generic CRUD mechanics, collection-query infrastructure, final presentation, shared interface shells, mapping UI, routing, or an external geocoding/postal-verification provider. Generic CRUD belongs to Cruding; provider integrations may feed Addressing validation evidence through explicit component contracts.

## Current posture

The component currently provides:

- address creation, scoped reads, soft deletion, cursor paging, and search;
- validation/provenance state and application of validated payloads;
- operational patches and batch operational updates;
- queue, country, source, validation, normalization, and governance-cluster summaries;
- evidence snapshots, outbox support, and address-index projection support;
- Objecting-backed system fields with Addressing-owned business entities and persistence;
- a standalone Symfony runtime plus `App\Addressing\AddressingBundle` for host integration.

The runtime does not perform provider-side geocoding, reverse geocoding, postal verification, or map rendering.

## Runtime HTTP surface

The standalone front controller currently exposes:

- `GET|POST /address/manage`
- `POST /api/address`
- `GET /api/address/page`
- `GET /api/address/search`
- `GET /api/address/queue-summary`
- `GET /api/address/country-portfolio`
- `GET /api/address/source-portfolio`
- `GET /api/address/validation-portfolio`
- `GET /api/address/normalization-portfolio`
- `POST /api/address/operational-batch`
- `GET|PATCH|DELETE /api/address/{id}`
- `POST /api/address/{id}/validated`
- `GET /api/address/{id}/governance-cluster`

`{id}` is the Addressing string identifier accepted by the runtime (`ULID`, with the local `demo-####` fixture form also accepted in standalone/demo flows).

Run `composer report:route-inventory` to inspect the route grammar extracted from the current front controller. `composer qa:trust-surface` fails when the diagnostic no longer proves the core runtime route surface.

## Local setup

```bash
composer install
composer qa:full
composer gating
```

For local demo data reset:

```bash
php bin/address-demo-reset 50
```

## Local Composer path installation

Development consumers use the canonical first-party path identity:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../Addressing",
      "options": {
        "symlink": true,
        "versions": { "addressing/address": "dev-master" }
      }
    }
  ],
  "require": {
    "addressing/address": "dev-master"
  }
}
```

Production consumers use the packaged dependency contract and do not rely on sibling path repositories.

## Documentation map

- [HTTP surface](docs/addressing-http-surface.md)
- [Entity boundary contract](docs/addressing-entity-boundary-contract.md)
- [Architecture remediation history](docs/addressing-architecture-remediation-plan.md)
- [Canonical OpenAPI contract](openapi/address.yaml)
