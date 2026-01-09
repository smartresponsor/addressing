# Address — E6 Read-Model & Repository (AddressIndex)

Generated: 2025-10-28T06:42:21

What:

- Read-model DTO: src/Projection/AddressIndex/IndexRecord.php
- Repository: RepositoryInterface + PdoRepository (PDO, parameterized)
- Projector: src/Projection/AddressIndex/Projector.php (from normalized+geocode)
- Schemas: schema.mysql.sql (MySQL InnoDB, utf8mb4) + schema.sqlite.sql (tests)
- CLI: tools/index/preview.php (no DB), tools/index/demo-sqlite.php "<addr>" [dbfile]
- Tests: tests/Projection/AddressIndexRepositoryTest.php (SQLite in-memory)

Notes:

- MySQL upsert: replace ON CONFLICT with ON DUPLICATE KEY UPDATE if using MySQL driver.
  Example:
  INSERT ... ON DUPLICATE KEY UPDATE line1=VALUES(line1), ... updated_at=VALUES(updated_at);
- geo_key: simple lat/lon rounded to five decimals as string; can be swapped to geohash later.

Run:
composer install
composer run test
php tools/index/preview.php "123 Main St, Houston, TX 77002, USA"
php tools/index/demo-sqlite.php "123 Main St, Houston, TX 77002, USA" ./address-index.sqlite

