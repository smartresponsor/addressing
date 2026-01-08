Automation kit (Smartresponsor-style)

What is included:

- Domain/Tool scripts: run.ps1, github-gate.ps1, evidence-pack.ps1, plan/codex helpers
- GitHub Actions workflow templates (tool/template/github-workflow)
- Cloudflare Worker trigger templates (tool/template/worker)

Intended workflow:

- Gate on PR/push (soft by default, strict optional via SR_GATE_STRICT=1)
- Agent dispatch (workflow_dispatch) that calls a reusable workflow (addressing-agent-run.yml) to prepare plan/codex or
  open a PR (see addressing-agent-dispatch.yml)
- Evidence release on tag (addressing-*) with provenance attestation + release asset (addressing-evidence-release.yml)
  Bootstrap:
- Copy this kit into a repo, then run:
  pwsh -NoProfile -File Domain/Tool/bootstrap-automation.ps1 -Domain <domain> -Sketch <n> -Owner <ghOwner>
  -Repo <ghRepo> -Force
