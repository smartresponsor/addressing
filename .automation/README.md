# .automation

This folder is intended to be vendored into platform component repositories.

- Keeps the repo root clean.
- Designed to sync/update automation tooling without touching application sources.

Entry point used by GitHub Actions:
- `.automation/tool/automater-pack-sync.ps1`
