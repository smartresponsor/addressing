# Address demo data

This project ships two different demo-data paths and they serve different purposes.

## Runtime fixture reset

- `bin/address-demo-reset` is the active runtime entrypoint.
- It boots the current Symfony container, resets the active schema through `AddressSchemaManager`, and loads generated demo addresses through `AddressDemoFixtureService`.
- This path is the correct one when you want to validate the full runtime cycle (`schema reset -> service write -> evidence/outbox side effects`).
- The generated fixture surface is intentionally richer than the tiny SQL sample: it exercises governance, validation, and revalidation columns that matter for the current RC slice.

## Static Postgres sample seed

- `sql/postgres/seed_demo.sql` is a manual sample seed for PostgreSQL.
- It is **not** part of the ordered migration authority and is intentionally excluded from `bin/address-migrate` and `AddressSchemaManager::ensureSchema()`.
- Use it only when you explicitly want a tiny deterministic SQL sample dataset.
- The seed is idempotent (`ON CONFLICT DO NOTHING`) so it can be re-applied during manual demos without polluting the table with duplicates.

## Usage

Runtime fixture reset:

```bash
bin/address-demo-reset 50
```

Manual Postgres sample seed after migrations:

```bash
export PG_DSN='pgsql:host=127.0.0.1;port=5432;dbname=addressing'
export PG_USER='addressing'
export PG_PASS='addressing'

php -r '$pdo=new PDO(getenv("PG_DSN"), getenv("PG_USER"), getenv("PG_PASS"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); $pdo->exec(file_get_contents("sql/postgres/seed_demo.sql")); echo "seeded
";'
```
