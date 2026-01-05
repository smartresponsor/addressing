# Changelog

## [1.0.0] - 2025-10-04
### Added
- Address domain entities, repositories, validators.
- AddressEngine with strategies (UA, CA, AU).
- AddressFormatter facade + factory + contract.
- PHPUnit, Behat, Integration, E2E tests.
- CI/CD: GitHub Actions + GitLab CI.
- Quality: SonarCloud, PHPStan, Psalm, Allure, Codecov.
- Pre-commit hooks.
- Dockerfile for Allure Reports.

### Fixed
- Namespace cleanup in Format/Contract/Factory/Strategy.
- Refactoring legacy `AddressFormatStrategy*` → `AddressFormatter*`.

### Notes
- First stable commercial-ready release.
