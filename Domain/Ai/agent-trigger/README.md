Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

Address agent API trigger (Cloudflare Worker) for GitHub workflow_dispatch.

Goal
- Trigger Address workflows via a simple HTTPS endpoint (no PR comments).
- Worker verifies request signature (HMAC SHA-256) + timestamp.
- Worker calls GitHub REST API: Create a workflow dispatch event.

Prerequisite
- In this repository, commit the workflow:
  .github/workflows/address-agent-dispatch.yml

Cloudflare Worker secrets (set in Cloudflare dashboard or via wrangler secret put)
- SR_TRIGGER_SECRET         Shared secret used to sign inbound requests.
- GH_TOKEN                  Fine-grained PAT recommended. Needs repository permission: Actions (write).
- GH_OWNER                  Example: taa0662621456
- GH_REPO                   Example: addressing
- GH_WORKFLOW               Example: address-agent-dispatch.yml
Optional Worker variables
- GH_REF                    Default branch ref (default: master)
- SR_TIME_SKEW_SEC          Allowed clock skew in seconds (default: 300)
- SR_ALLOWED_TASK           Comma-separated allow list (default: scan,health,doctor,validate,plan,codex)

Deploy (Wrangler)
- Put this folder as a standalone Worker project or copy Domain/Ai/agent-trigger/worker to a separate repo.
- Create secrets (recommended):
  wrangler secret put SR_TRIGGER_SECRET
  wrangler secret put GH_TOKEN
- Set vars in wrangler.toml or via dashboard:
  GH_OWNER, GH_REPO, GH_WORKFLOW, GH_REF

API
POST /dispatch
Headers
- X-SR-Timestamp: unix seconds
- X-SR-Signature: hex(hmac_sha256(secret, "{ts}.{rawBody}"))
Body (JSON)
{
  "task": "plan",
  "ref": "master",
  "inputs": { "note": "optional string" }
}

Response
- 200 JSON on success. GitHub dispatch itself returns 204 (no run id).
- /health returns ok.

Local client (PowerShell)
- Use Domain/Tool/trigger-dispatch.ps1
  $env:SR_TRIGGER_SECRET="..."
  .\Domain\Tool\trigger-dispatch.ps1 -Url "https://<worker>.<subdomain>.workers.dev/dispatch" -Task plan -Ref master
