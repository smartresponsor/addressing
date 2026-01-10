Address Data — промежуточный портрет (после чистки)

1) Что было вычищено

- Убраны части, которые не относятся к Address Data: Geocode integration, parse/normalize/engine-пакеты,
  index/preview/patch-tools, старые контроллеры/DTO и мусорные overlay-остатки.
- Убраны дубли классов и файлы с суффиксами (1), *.bak и т.п. (вне vendor).
- Починен синтаксис (php -l проходит для всего src/public/tools/tests вне vendor).

1) Что осталось как ядро Address Data

- Данные: src/Entity/Address/AddressData.php (+ src/EntityInterface/Address/AddressInterface.php).
- Репозиторий: src/Repository/Address/AddressRepository.php (PDO) + зеркальный интерфейс
  src/RepositoryInterface/Address/AddressRepositoryInterface.php.
- Контракт “валидировано”: src/Contract/Address/AddressValidated.php.
- Применение валидации: src/Service/Address/AddressValidatedApplier.php + зеркальный интерфейс
  src/ServiceInterface/Address/AddressValidatedApplierInterface.php.
- Проекции/аутбокс: src/Service/Address/AddressProjection.php, src/Service/Address/AddressOutboxDrainer.php,
  bin/address-* runner scripts.
- HTTP API: src/Http/AddressApi/Controller.php, public/index.php.
- OpenAPI: openapi/address.yaml.

1) Текущее API (по openapi/address.yaml)
   /api/address:
   /api/address/page:
   /api/address/{id}:
   /api/address/{id}/validated:

2) Хранилище (как сейчас в sql/*)

- Postgres: address_entity (источник правды).
- MySQL: address_projection (read model).

1) Ключевые пробелы (до “product/production-ready” уровня)

- Нет аутентификации/авторизации (OIDC/JWKS, RBAC роли, tenant-изоляция по умолчанию).
- Нет идемпотентности на write-эндпойнтах (idempotency-key + store).
- Нет стабильной схемы ошибок (problem+json), нет трассировки/метрик, нет SLO-гейта.
- Тесты минимальны (нужно восстановить слой unit+smoke под Address Data).

1) Следующие шаги (коротко, как конверты)

- ATOM: “Auth+Tenant guard” (OIDC/JWKS middleware + ownerId enforcement).
- BUCKET: “Idempotency+Outbox gate” (write safety, retries, DLQ).
- BUCKET: “Observability+Contract polish” (metrics/tracing + problem+json + расширить OpenAPI).

Файлы отчёта:

- report/addressdata-clean-removed-path.txt
- report/addressdata-clean-stats.json
