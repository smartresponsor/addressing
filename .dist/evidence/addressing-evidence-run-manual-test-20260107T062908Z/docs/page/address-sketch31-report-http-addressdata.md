Address-sketch31-9-http-addressdata

Goal
- Expose AddressData CRUD and page endpoints over HTTP.
- Keep the validated-apply contract inside AddressData (Locator/Engine can call it).

Prerequisite
- Apply Address-sketch31-4-compile-repair and Address-sketch31-8-runtime-fix first.

Endpoints
- POST /api/address
- GET /api/address/page
- GET /api/address/search (alias of /page)
- GET /api/address/{id}
- DELETE /api/address/{id}
- POST /api/address/{id}/validated

Notes
- Rate limiting remains SQLite-local (var/rate.sqlite) to avoid SQL dialect issues on Postgres.
