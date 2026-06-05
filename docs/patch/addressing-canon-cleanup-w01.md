# Addressing canon cleanup W01

Applied to current slice: `Addressing(9).zip`.

## Fixed

- Removed empty folders: `sql/mysql/`, `tests/Integration/`.
- Removed local cache artifacts: `.deptrac.cache`, `.php-cs-fixer.cache`, `.phpunit.result.cache`.
- Removed dead generic shells: `RootEntity`, `RootEntityInterface`, `ObjectTrait`, `ObjectAuditTrait`, unused `StructuredLogger`.
- Moved `src/Config/geocode.sample.php` to `config/samples/addressing_geocode.sample.php`.
- Removed deploy-blocking `objecting/object` dependency and local `../Objecting` repository from Composer metadata and lock.
- Removed missing `.commanding` script references from Composer scripts.
- Repaired `public/index.php` so it no longer depends on missing `AddressPdoFactory`; `AddressRateLimiter` is resolved from the Symfony container.
- Renamed record/read-model classes:
  - `App\Entity\Record\AddressEntity` -> `App\Entity\Record\AddressRecord`
  - `App\Entity\Record\AddressEvidenceSnapshotEntity` -> `App\Entity\Record\AddressEvidenceSnapshotRecord`
- Moved `AddressPageCriteria` out of `RepositoryInterface` into `App\Value\Persistence`.
- Canon-prefixed generic HTTP/projection classes:
  - `ErrorMap` -> `AddressErrorMap`
  - `Cors` -> `AddressCorsMiddleware`
  - `IpGuard` -> `AddressIpGuardMiddleware`
  - `RateLimiter` -> `AddressRateLimiter`
  - `RequestId` -> `AddressRequestIdMiddleware`
  - `SecurityHeaders` -> `AddressSecurityHeadersMiddleware`
  - `Validator` -> `AddressSchemaValidator`
  - `IndexProjector` -> `AddressIndexProjector`
  - `IndexRecord` -> `AddressIndexRecord`
  - `Normalizer` -> `AddressIndexNormalizer`
  - `Projector` -> `AddressIndexProjectorService`
  - `RepositoryInterface` -> `AddressIndexRepositoryInterface`
- Normalized test namespaces to `Tests\...`.
- Expanded deptrac layer coverage for actual folders: `Projection`, `Bridge`, `Infrastructure`, `Fixture`, `Util`, `UtilInterface`.

## Verified locally

- PHP lint clean for `src`, `tests`, `public`, `bin`, `tools`.
- Local `App\...` imports resolve against current `src` class map.
- No empty folders remain.
- No class/file-name mismatches remain in `src`.

## Deferred intentionally

- Large SRP/ISP split of `DoctrineAddressRepository` remains for the next pass.
- `Integration/Console/Command` -> `Command` migration remains for a separate runtime-aware pass.
- `Bridge/Doctrine` vs `Infrastructure/Doctrine` consolidation remains deferred.
