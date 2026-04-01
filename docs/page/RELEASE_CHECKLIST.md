# ✅ Release Checklist — AddressEngine v1.0.0

## 1. Подготовка кода

- [x] Рефакторинг `Format/Contract/Factory/Strategy` — чистая архитектура.
- [x] Фасад `AddressFormatter` подключён к новым namespace.
- [x] Unit, Integration и E2E тесты покрывают основные сценарии.
- [x] Pre-commit hook защищает от сырого кода.

## 2. CI/CD

- [x] **GitHub Actions**
    - PHPUnit, Integration, E2E.
    - Allure Reports.
    - Static analysis (PHPStan, Psalm).
    - Codecov (coverage).
    - SonarCloud (quality gates).
    - Release workflow (по тегу).

- [x] **GitLab CI**
    - Тесты.
    - Allure.
    - Sonar.
    - Coverage → Codecov.

## 3. Качество и отчёты

- [x] Allure Reports (локально через Docker, CI → artifacts).
- [x] Static Analysis Reports (PHPStan, Psalm → docs).
- [x] SonarCloud подключён (Code Smells, Coverage, Maintainability).
- [x] Codecov бейдж в README.

## 4. Dev Workflow

- [x] Pre-commit hooks (`php -l`, phpstan, psalm, phpunit).
- [x] Dependabot (Composer + Actions).
- [x] Documentation:
    - Installation (Composer require).
    - Usage (Formatter, Validator, API).
    - QA & Reports.
    - Static Analysis Demo.
    - Demo Allure Report.

## 5. Релиз

- [x] CHANGELOG.md создан.
- [x] GitHub Release workflow настроен (генерация релиза по тегу).
- [x] Packagist auto-update включён (API Token).
- [ ] Добавить `secrets.PACKAGIST_TOKEN` в GitHub Secrets.
- [ ] Добавить `secrets.CODECOV_TOKEN` и `secrets.SONAR_TOKEN`.

## 6. Финальные шаги

1. Проверить `composer.json`:
   ```json
   {
   "name": "your-org/address-engine",
   "type": "library",
   "license": "MIT"
   }
   ```  
2. Сделать последний прогон CI (GitHub Actions + GitLab CI).
3. Создать тег:
   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```  
4. Дождаться автоматического релиза и обновления на Packagist.
5. Проверить бейджи SonarCloud, Codecov, GitHub Actions.

---

⚡ После этого AddressEngine станет **production-ready v1.0.0**, доступным через Composer и полностью интегрированным в
CI/CD.  
