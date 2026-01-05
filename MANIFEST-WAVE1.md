# Address — Wave-1 kit (E1–E9)

Generated: 2025-10-28T07:05:38

Includes overlay for early envelopes:
- E1–E2 Value Objects: Line, Region, Postal, Country (src/Value/**)
- E3 Normalizer: src/Service/Normalize/Normalizer.php
- E4 Parser: src/Service/Parse/Parser.php
- E5 Geocode: Integration/Geocode (from previous)
- E6 Read-model & Repository: Projection/AddressIndex/**
- E7 Events & Projector wiring: Domain/**/Event/**, IndexProjector
- E8 HTTP API + OpenAPI: public/index.php, openapi/address.yaml
- E9 API tests, ErrorMap, RateLimiter, Postman

How to use (overlay repo):
1) Unzip into your component root (preserving src/, tools/, tests/, public/, openapi/).
2) If using SQLite demo: ensure tools/index/address-index.sqlite exists or let API create it.
3) Run tests: composer install && composer run test
4) Run API: php -S 127.0.0.1:8080 -t public
