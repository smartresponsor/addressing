# Address — E7 Domain Events → Index projector wiring

Generated: 2025-10-28T06:44:54

Domain events:

- src/Domain/Address/Event/AddressCreated.php
- src/Domain/Address/Event/AddressUpdated.php
- src/Domain/Shared/Event/EventInterface.php
- src/Domain/Shared/Event/DispatcherInterface.php
- src/Domain/Shared/Event/InMemoryDispatcher.php (simple pub/sub for tests)

Projector:

- src/Projection/AddressIndex/IndexProjector.php — handles AddressCreated/Updated, runs Normalizer (+optional Geocode),
  upsert via RepositoryInterface

CLI batch:

- tools/index/reindex-csv.php <file.csv> [dbfile]   # expects headers: line1,line2,city,region,postal,country
- tools/index/reindex-ndjson.php <file.ndjson> [dbfile]  # lines with same keys
  NET=1 enables live geocoding via NominatimAdapter

Tests:

- tests/Projection/IndexProjectorTest.php — SQLite in-memory; validates upsert and search

How to run locally:
composer install
composer run test
php tools/index/reindex-csv.php my.csv ./address-index.sqlite
php tools/index/reindex-ndjson.php data.ndjson ./address-index.sqlite

Notes:

- No stubs; production swap for Dispatcher/Geocode via DI.
- Geocoding optional to avoid network in CI.
