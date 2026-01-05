Address CI gate (step 13)

What it does
- Runs Domain overlay checks on every PR/push to master:
  - doctor, scan, health, validate
- Uploads report/ as CI artifact

Requirements
- Domain overlays (Address-sketch32-4 .. 12) applied in repo root.
- Canon CLI must be available via composer dev dependency (vendor/bin/*), or your Domain tools must support SR_CANON_SCAN_CMD.
