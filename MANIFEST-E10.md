# Address — E10 Security & Ops overlay

Generated: 2025-10-28T17:28:43

Adds:
- Middleware: RequestId, IpGuard, SecurityHeaders, Cors
- Structured logs (NDJSON): src/Tools/Log/StructuredLogger.php → LOG_PATH or tools/log/address-api.ndjson
- Input schema guard: src/Http/Schema/Validator.php (ValidateRequest, ParseRequest)
- Error registry: openapi/error-codes.yaml
- Patch script: tools/patch/e10-apply.php — вносит правки в public/index.php и Controller::jsonInput()

Apply:
1) Распаковать ZIP в корень Address-компонента (поверх).
2) php tools/patch/e10-apply.php
3) Перезапустить `php -S 127.0.0.1:8080 -t public`

Env:
  CORS_ALLOW_ORIGINS='*' | 'https://app.example.com,https://admin.example.com'
  CORS_ALLOW_METHODS='GET,POST,OPTIONS'
  CORS_ALLOW_HEADERS='Content-Type,Authorization,X-Request-Id'
  CORS_ALLOW_CREDENTIALS=0|1
  ALLOW_IPS='1.2.3.4,5.6.7.8' (empty = allow all)
  DENY_IPS='10.0.0.1'
  ALLOW_PATHS='/status,/metrics' (optional)
  LOG_PATH='/var/log/address-api.ndjson'
  CSP="default-src 'none'; frame-ancestors 'none'; base-uri 'none'"
  HSTS=1
