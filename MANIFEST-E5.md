# Address — E5 Geocoding abstraction + Nominatim adapter

Generated: 2025-10-28T06:40:30

Interfaces & VOs:
- src/Integration/Geocode/GeocodeInterface.php
- src/Integration/Geocode/GeocodeResult.php
- src/Integration/Geocode/Http/HttpClientInterface.php
- src/Integration/Geocode/Http/SimpleHttpClient.php (no external deps)

Adapter:
- src/Integration/Geocode/NominatimAdapter.php — forward/forwardByParts/reverse; env-driven base URL & User-Agent; maps to GeocodeResult

Config:
- src/Config/geocode.sample.php (uses env: GEOCODE_NOMINATIM_URL, GEOCODE_USER_AGENT). No secrets committed.

CLI:
- tools/geocode/forward.php "<query>" [country]
- tools/geocode/reverse.php <lat> <lon>

Tests:
- tests/Geocode/NominatimAdapterTest.php (FakeHttp to simulate API; no network).

Run:
  composer install
  composer run test
  php tools/geocode/forward.php "10 Downing St, London" GB

Notes:
- Обязателен корректный User-Agent для Nominatim. Реальный HTTP-клиент — SimpleHttpClient; в проде можно заменить на Guzzle.
- Rate limiting/Retry можно добавить на адаптер (E5+), сейчас минимальный каркас без sleep.
