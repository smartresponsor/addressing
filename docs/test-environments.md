# Test environments (local Postgres and Docker)

## Local
1. Export test database variables:
   - `TEST_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=addressing_test"`
   - `TEST_DB_USER="addressing"`
   - `TEST_DB_PASS="addressing"`
2. Run test suites via composer scripts.

If `TEST_DB_DSN` is empty, integration/functional tests use in-memory SQLite.

## Docker
Use `docker-compose.yml` with bundled PostgreSQL service:

```bash
docker-compose up --build --abort-on-container-exit
```

The app container runs PHPUnit with `TEST_DB_*` variables pointed to the `db` container.
