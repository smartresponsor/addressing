# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Plan = "report/address-ai-plan.md",
  [string]$Out = "report/address-codex-prompt.txt",
  [string]$ReviewOut = "report/address-codex-review.md"
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

if (-not (Test-Path $Plan)) {
  throw "Plan file not found: $Plan"
}

Ensure-Dir (Split-Path -Parent $Out)

$planText = Get-Content -Raw -Path $Plan

$prompt = @"
You are Codex running in ANALYSIS-ONLY mode.
Task: review the plan below and produce a patch checklist, but DO NOT modify any files.

Output:
1) Write a single markdown file at $ReviewOut with:
   - a prioritized checklist
   - a list of file moves/renames (respect singular naming)
   - a list of risky operations
   - a minimal set of commands to run for validation (composer, phpunit, lint)
2) Do NOT apply changes.

Constraints (SmartResponsor canon):
- mirror layer interfaces under src/*Interface/
- English-only code comments
- no plural in class/method name
- file names: single hyphen, avoid double hyphen
- NEVER create or use src/Domain/*

Plan:
----------------
$planText
----------------
"@

Set-Content -Path $Out -Value $prompt -Encoding UTF8

Write-Host "OK: $Out"
