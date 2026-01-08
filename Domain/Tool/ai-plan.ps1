# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Domain = "",
  [string]$Scan = "",
  [string]$Out = ""
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ([string]::IsNullOrWhiteSpace($Path)) { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Resolve-Domain([string]$d) {
  if (-not [string]::IsNullOrWhiteSpace($d)) { return $d }
  if ($env:SR_DOMAIN -and -not [string]::IsNullOrWhiteSpace($env:SR_DOMAIN)) { return $env:SR_DOMAIN.Trim() }
  return "component"
}

$Domain = Resolve-Domain $Domain

if ([string]::IsNullOrWhiteSpace($Scan)) {
  $Scan = ("report/{0}-canon-scan.json" -f $Domain)
}

if ([string]::IsNullOrWhiteSpace($Out)) {
  $Out = ("report/{0}-ai-plan.md" -f $Domain)
}

Ensure-Dir (Split-Path -Parent $Out)

$scanObj = $null
if (Test-Path $Scan) {
  try { $scanObj = Get-Content $Scan -Raw | ConvertFrom-Json } catch { $scanObj = $null }
}

$lines = @()
$lines += ("# AI plan ({0})" -f $Domain)
$lines += ""
$lines += ("Generated: {0} UTC" -f (Get-Date).ToUniversalTime().ToString("o"))
$lines += ""

if ($scanObj -and $scanObj.status -eq "skipped") {
  $lines += "Canon scan: skipped (canon CLI missing)."
  $lines += "Next: set SR_CANON_SCAN_CMD or add Canon as dev dependency, then re-run scan."
  $lines += ""
} else {
  $lines += "Canon scan: present (or unknown format)."
  $lines += ""
}

$lines += "Requested outputs:"
$lines += "- A small patch proposal focusing only on Domain tools/workflows/docs."
$lines += "- No application/business code changes unless explicitly requested."
$lines += ""
$lines += "Constraints:"
$lines += "- Single-hyphen filenames."
$lines += "- English-only code comments."
$lines += "- Keep changes within allowlisted paths (Domain/, .github/workflows/, docs/)."
$lines += ""
$lines += "Suggested next steps:"
$lines += "1) Review report/*.json artifacts."
$lines += "2) Create or update agent PR workflow."
$lines += "3) Create evidence release workflow for tag-based evidence packs."

($lines -join "`n") | Set-Content -Path $Out -Encoding UTF8
Write-Host "OK: $Out"
exit 0
