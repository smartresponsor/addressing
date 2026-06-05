# Addressing Doctrine repository transition

This wave moves the component from a decorative Doctrine authority surface to a real **Doctrine-backed repository entrypoint**.

## What changed

- `AddressRepositoryInterface` now resolves to `DoctrineAddressRepository`
- core lifecycle methods are now Doctrine-backed:
  - `create`
  - `update`
  - `get`
  - `delete`
  - `findByDedupeKey`
  - `appendEvidenceSnapshot`
  - `getLatestEvidenceSnapshot`
  - `findEvidenceHistoryPage`
- high-volume portfolio/search/reporting methods still delegate to the legacy PDO repository in this transition wave

## What this means

Hosted applications now consume narrow application services such as `AddressReadService`, `AddressWriteService`, and summary-specific services through Doctrine-led repository implementations.

This is a **transition wave**, not the final Doctrine-only state.
