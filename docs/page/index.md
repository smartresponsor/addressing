# Addressing

This repository is the reference component for end-to-end automation in SmartResponsor:

- Domain overlay gates (Canon/Health/Ai)
- CI checks on PR
- Docs deployment via Cloudflare Pages

## Quick checks (local)

```powershell
.\Domain\Tool\run.ps1 doctor
.\Domain\Tool\run.ps1 scan
.\Domain\Tool\run.ps1 health
.\Domain\Tool\run.ps1 validate
```

## Docs

- [Address business flows](address-business-flows.md)
- [Address data model](address-data-model.md)
- [Address outbox event contracts](address-outbox-events.md)
