# Test environments (local Postgres and Docker)

## Runtime posture

The current Addressing slice is PDO-first at runtime. The Symfony container exposes the primary `PDO` service and does not wire a Doctrine ORM manager into the container surface.

For generic tooling compatibility, `tests/object-manager.php` returns the primary `PDO` connection for the active runtime slice, and `tests/console-application.php` boots the current Symfony kernel through the shared runtime bootstrap helper.

## Local

1. Export test database variables when you want PostgreSQL-backed tests:
   - `TEST_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=addressing_test"`
   - `TEST_DB_USER="addressing"`
   - `TEST_DB_PASS="addressing"`
2. Run the PHPUnit suites via the Composer scripts.

If `TEST_DB_DSN` is empty, integration and functional tests use SQLite.

## Docker

Use `docker-compose.yml` with the bundled PostgreSQL service:

```bash
docker-compose up --build --abort-on-container-exit
```

The app container runs PHPUnit with `TEST_DB_*` variables pointed to the `db` container.

## Smoke commands

The current trust-surface smoke entrypoints are:

- `composer smoke:runtime`
- `composer smoke:fixtures`
- `composer smoke:container`
- `composer smoke:fixture-load`
- `composer smoke:doctrine`
- `composer smoke:graphql`

Notes:

- `smoke:doctrine` is intentionally `not_applicable` in the current PDO-first runtime.
- `smoke:graphql` is intentionally `not_applicable` because no GraphQL surface is wired in the current slice.
- `composer fixtures:demo` now uses the current runtime-safe `bin/address-demo-reset` entrypoint.
- `bin/address-demo-reset-runtime` remains available as a direct replacement-safe entrypoint during the runtime synchronization period.
