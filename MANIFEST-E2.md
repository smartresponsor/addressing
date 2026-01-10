# Address — E2 Value Objects & Entity Alignment

Generated: 2025-10-28T06:28:10

Added VOs:

- src/Value/CountryCode.php (ISO 3166-1 alpha-2, uppercase, equals(), toKey())
- src/Value/Subdivision.php (A-Z0-9- up to 10 chars)
- src/Value/PostalCode.php (A-Z0-9- space, <=16 chars, normalized spaces)
- src/Value/StreetLine.php (1..160 chars)
- src/Value/GeoPoint.php (lat [-90..90], lon [-180..180])

Address entity:

- Refactored/created to accept & return VOs (API level). Internal storage may remain scalar until E7 migrations.
- Optional GeoPoint via withGeo().

Tests:

- phpunit.xml.dist
- tests/ValueObjectsTest.php (equals/hash-like & basic constraints)

Composer:

- require-dev: phpunit/phpunit ^10.5
- scripts: test

Run locally:
composer install
composer run test

