Address CI gate (step 13)

What it does
- Runs Domain overlay checks on every PR/push to master (strict):
  - doctor, scan, health, validate
- Runs quality gates (phpstan, psalm, rector:dry) and unit tests with coverage threshold (default 80%).
- Runs migration smoke checks for Postgres and MySQL projection.
- Uploads report/ as CI artifact

Requirements
- Domain overlays (Address-sketch32-4 .. 12) applied in repo root.
- Canon CLI must be available via composer dev dependency (vendor/bin/*), or your Domain tools must support SR_CANON_SCAN_CMD.
