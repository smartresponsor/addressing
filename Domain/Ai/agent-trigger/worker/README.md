# Addressing Agent Trigger Worker

## Secrets and vars

Required secrets:
- `SR_TRIGGER_SECRET_K1`
- `GH_TOKEN`

Required vars (set in `wrangler.toml` or dashboard):
- `GH_OWNER`
- `GH_REPO`
- `GH_WORKFLOW`

Optional vars:
- `GH_REF`
- `SR_NONCE_TTL_SEC` (default `600`)
- `SR_DEBUG`

## Local development

1. Copy `.dev.vars.example` to `.dev.vars` and set real secrets.
2. Start the worker:

```bash
cd Domain/Ai/agent-trigger/worker
wrangler dev
```

3. Trigger a request (PowerShell):

```powershell
$env:SR_TRIGGER_SECRET_K1="<secret>"
$env:GH_TOKEN="<token>"
.\Domain\Tool\sr-agent-call.ps1 -Url "http://127.0.0.1:8787/dispatch" -Task plan -Ref master
```

## Deploy

```bash
cd Domain/Ai/agent-trigger/worker
wrangler deploy
```
