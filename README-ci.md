Address CI gate (step 13)

What it does
- Runs Domain overlay checks on every PR/push to master:
  - doctor, scan, health, validate
- Uploads report/ as CI artifact

Requirements
- Domain overlays (Address-sketch32-4 .. 12) applied in repo root.
- Canon CLI must be available via composer dev dependency (vendor/bin/*), or your Domain tools must support SR_CANON_SCAN_CMD.

Coverage + Sonar thresholds
- PHPUnit в CI печатает coverage и проверяет минимум: Lines >= 80% (COVERAGE_MIN).
- Sonar quality gate включён (sonar.qualitygate.wait=true).
- В Sonar установлены минимальные пороги: Coverage >= 80%, New Code Coverage >= 80%, и 0 blocker/critical issues.
