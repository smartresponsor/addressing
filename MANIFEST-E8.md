# Address — E8 HTTP API + OpenAPI

Generated: 2025-10-28T06:55:37

Endpoints (public/index.php):

- POST /api/address/validate — нормализация структуры
- POST /api/address/parse — парсинг free-form → normalize
- POST /api/address/geocode — геокод (требует NET=1)
- GET /api/address/index/search — поиск read-model (DB_DSN)

Run:
php -S 127.0.0.1:8080 -t public

Env:
DB_DSN=sqlite:./tools/index/address-index.sqlite (по умолчанию)
DB_USER=... DB_PASS=... (для MySQL)
NET=1 (включить HTTP-вызовы геокодера)

Spec:

- openapi/address.yaml

Notes:

- Без Composer можно использовать tools/autoload.php (включено).
- Для геокода Nominatim нужен корректный User-Agent через env GEOCODE_USER_AGENT.
