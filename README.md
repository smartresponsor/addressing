# Addressing

Addressing is a Symfony-based geo-data and address management component of the Smart Responsor platform. It handles address lifecycle, validation, and advanced search operations within the system.

This module is **not** a mapping service or a routing engine. It acts as the core database layer and HTTP boundary for normalized, validated addresses.

## Current Posture

### What the component already does
- Handles address CRUD operations and schema mapping.
- Provides standard validation constraints for address models.
- Offers advanced search capabilities (by city, country, zipcode, etc.).
- Built on Doctrine attributes and Symfony standard libraries.
- Features standard QA scripts (PHPStan, Psalm, Rector, PHP-CS-Fixer, Deptrac).

### What this repository does not claim yet
- Interactive mapping or map rendering interfaces.
- Geocoding/reverse-geocoding service provider integrations.
- Address verification via third-party post-office APIs.

## Runtime Surface & Entrypoints

The versioned HTTP entrypoints for address operations include:
- `POST /address` - Create an address
- `GET /address/{id}` - Fetch a specific address by ID
- `GET /address/search` - Search addresses by city name
- `GET /address/list` - List addresses with pagination
- `POST /address/search-advanced` - Perform advanced filtering by criteria

## Local Setup

Install dependencies:
```bash
composer install
npm install
```

Run QA suites and validations:
```bash
composer qa:full
vendor/bin/phpunit -c phpunit.xml.dist
```

For console demo data reset:
```bash
php bin/address-demo-reset 50
```

## Local Composer Path Installation

To import this module as a path repository within your Symfony host project:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../Addressing",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "addressing/address": "*@dev"
  }
}
```

## Documentation Map

- [HTTP Surface Notes](docs/addressing-http-surface.md)
- [Entity Boundary Contract](docs/addressing-entity-boundary-contract.md)
- [Architecture Remediation Plan](docs/addressing-architecture-remediation-plan.md)
- [OpenAPI Schema Definition](docs/openapi.yaml)
