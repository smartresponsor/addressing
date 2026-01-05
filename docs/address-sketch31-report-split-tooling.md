Address-sketch31-10-split-tooling

Goal
- Give you a safe, repeatable way to split the current mixed Address repo into:
  - address-data
  - address-engine
  - address-locator

What you get
- tools/address-classify.ps1: produces a CSV report (read-only).
- tools/address-split.ps1: copies files into ./split/...; with -Purge it deletes originals.

Default behavior (safe)
- Copy-only. No deletions.

Suggested workflow
1) From repo root:
   - powershell -ExecutionPolicy Bypass -File tools\address-classify.ps1
   - open report\address-split-classify.csv
2) If classification looks ok:
   - powershell -ExecutionPolicy Bypass -File tools\address-split.ps1
3) Create new repos from split/address-data, split/address-engine, split/address-locator.

Notes
- Some src/* files are marked as legacy because the current layout is not Symfony/SmartResponsor-canonical. Move them into the right layers inside the target repo.
