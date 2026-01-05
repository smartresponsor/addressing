# Address — E9 API smoke + RateLimit + ErrorMap + Postman

Generated: 2025-10-28T06:58:03

Adds:
- src/Http/ErrorMap.php — uniform JSON errors
- src/Http/Middleware/RateLimiter.php — per-IP per-route minute window (limit via RATE_LIMIT_PER_MIN, burst via RATE_LIMIT_BURST)
- public/index.php — wired RateLimiter + ErrorMap (429/404)
- src/Http/AddressApi/ControllerTestShim.php — helper for unit tests
- tests/Http/ApiSmokeTest.php — validate/parse smoke + limiter test
- openapi/address.postman_collection.json — 4 requests, baseUrl var

Run tests:
  composer install
  composer run test

Run API:
  php -S 127.0.0.1:8080 -t public
  # optional: RATE_LIMIT_PER_MIN=120 RATE_LIMIT_BURST=60 NET=1 DB_DSN=sqlite:./tools/index/address-index.sqlite
