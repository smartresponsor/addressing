Domain/ (root) — SmartResponsor overlay (NOT Symfony project structure).

What lives here
- Plugin manifests for ecosystem components (Health / Canon / AI)
- Offline repo-local tools (PowerShell wrappers) under Domain/Tool/
- Prompts and policies under Domain/Ai/prompt/

What must NOT live here
- Application code (entities, controllers, services, etc.)
- Secrets/keys/tokens

Quick start (PowerShell)
1) Canon scan (writes JSON to report/):
   .\Domain\Tool\ai-scan.ps1

2) Canon gate (fails on violations):
   .\Domain\Tool\canon-check.ps1

3) Health sample (writes JSON to report/):
   .\Domain\Tool\health-sample.ps1

4) AI plan-only (writes markdown plan to report/; requires OPENAI_API_KEY):
   $env:OPENAI_API_KEY = "sk-..."
   .\Domain\Tool\ai-plan.ps1

5) Codex review prompt generator (writes prompt to report/; no code changes):
   .\Domain\Tool\ai-codex-review.ps1 -Plan report/address-ai-plan.md -Out report/address-codex-prompt.txt

6) Apply gate (refuses unless explicitly enabled):
   $env:SR_ALLOW_APPLY = "1"
   .\Domain\Tool\ai-apply.ps1 -Patch report/address.patch

Outputs
- All generated artifacts go into ./report/ (gitignored).

Safety
- ai-plan.ps1 is plan-only.
- ai-codex-review.ps1 is analysis-only.
- ai-apply.ps1 requires SR_ALLOW_APPLY=1.
