# Address demo data

This project includes a deterministic demo seed file for Postgres and a reset helper script.

## Files

- `sql/postgres/seed_demo.sql` holds two sample rows in `address_entity` and two entries in `address_outbox`.
- `bin/address-demo-reset` truncates `address_entity` and `address_outbox`, then re-seeds them from the demo SQL.

## Usage

```bash
export PG_DSN='pgsql:host=127.0.0.1;port=5432;dbname=addressing'
export PG_USER='addressing'
export PG_PASS='addressing'

bin/address-demo-reset
```

The reset script is idempotent for demo use and can be re-run whenever you need fresh demo rows.
