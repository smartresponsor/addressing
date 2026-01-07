# 📢 AddressEngine v1.0.0 — Official Release

## 🎯 Overview
AddressEngine — это лёгкая, расширяемая и production-ready библиотека для работы с адресами:  
- Валидация и нормализация.  
- Форматирование с учётом стран (UA, CA, AU).  
- Фасад `AddressFormatter` + фабрика стратегий.  
- Полностью покрыто тестами (Unit, Integration, E2E, Behat).  

## ✨ Key Features
- **Domain Entities & Services**: чистая архитектура (контракты, фабрики, стратегии).  
- **Validation & Formatting**: гибкие правила для разных стран.  
- **Testing Suite**: Unit + Integration + E2E + BDD (Behat).  
- **Reports & QA**: Allure, PHPStan, Psalm, SonarCloud, Codecov.  
- **CI/CD Ready**: GitHub Actions + GitLab CI.  
- **Dev Workflow**: pre-commit hooks, Dependabot.  
- **Docs & Samples**: примеры отчётов (Allure, PHPStan, Psalm).  

## 🛠 Installation
```bash
composer require your-org/address-engine
```

## 🚀 CI/CD Integrations
- ✅ GitHub Actions (PHPUnit, Behat, Allure, Coverage).  
- ✅ GitLab CI (Sonar, Behat, Codecov).  
- ✅ Auto release to Packagist.  
- ✅ Pre-commit hooks (phpstan, psalm, phpunit).  

## 📊 Quality Gates
- SonarCloud: Code Smells, Coverage, Maintainability.  
- Codecov: Coverage reports with badge.  
- Allure: Красивые отчёты в CI и локально (Docker).  

## 📌 Changelog
### [1.0.0] - 2025-10-04
- Initial stable release.  
- Address domain entities, repositories, validators.  
- Formatter (Contract/Factory/Strategy).  
- Full QA pipeline.  
- Docs, CI/CD, Release workflow, Packagist auto-update.  

---

⚡ **AddressEngine v1.0.0** — первый стабильный релиз, готовый к использованию в продакшене.  
