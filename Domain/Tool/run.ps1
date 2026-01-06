# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [Parameter(Mandatory=$true, Position=0)]
  [string]$Action,
  [Parameter(ValueFromRemainingArguments=$true)]
  [string[]]$Arg
)

$ErrorActionPreference = "Stop"

function Print-Help {
  $txt = @"
Domain overlay runner

Usage:
  .\Domain\Tool\run.ps1 help
  .\Domain\Tool\run.ps1 scan     [-Out report/address-canon-scan.json]
  .\Domain\Tool\run.ps1 health   [-Repo .] [-Out report/address-health-sample.json]
  .\Domain\Tool\run.ps1 doctor   [-Repo .] [-Out report/address-doctor.json]
  .\Domain\Tool\run.ps1 validate [-Repo .] [-Out report/address-report-validate.json] [-AllowMissing]
  .\Domain\Tool\run.ps1 plan     [-Scan report/address-canon-scan.json] [-Out report/address-ai-plan.md]
  .\Domain\Tool\run.ps1 codex    [-Plan report/address-ai-plan.md] [-Out report/address-codex-prompt.txt]
  .\Domain\Tool\run.ps1 apply    -Patch <file.patch> [-Repo .] [-TestCmd "<cmd>"] [-NoCanonCheck]

Notes:
- All commands are repo-local and should not modify application code unless you run "apply".
- "apply" requires SR_ALLOW_APPLY=1.

"@
  Write-Host $txt
}

# --- robust ToolDir (CI-safe) ---
$ToolDir = $null

if ($PSCommandPath -and $PSCommandPath.Trim() -ne "") {
  $ToolDir = Split-Path -Parent $PSCommandPath
} elseif ($MyInvocation -and $MyInvocation.MyCommand -and $MyInvocation.MyCommand.Path -and $MyInvocation.MyCommand.Path.Trim() -ne "") {
  $ToolDir = Split-Path -Parent $MyInvocation.MyCommand.Path
} elseif ($PSScriptRoot -and $PSScriptRoot.Trim() -ne "") {
  $ToolDir = $PSScriptRoot
}

# last resort: repo-relative resolve (works in GitHub Actions)
if (-not $ToolDir -or $ToolDir.Trim() -eq "" -or -not (Test-Path $ToolDir)) {
  $ToolDir = (Resolve-Path "Domain/Tool").Path
}

# lock CWD to repo root
$RepoRoot = (Resolve-Path (Join-Path $ToolDir "../..")).Path
Set-Location $RepoRoot
# --- end robust ToolDir ---

function Call-Tool([string]$Name, [string[]]$A) {
  if (-not $ToolDir -or $ToolDir.Trim() -eq "") { throw "ToolDir is empty (cannot resolve Domain/Tool)." }
  $p = Join-Path $ToolDir $Name
  if (-not (Test-Path $p)) { throw "Tool not found: Domain/Tool/$Name" }
  & $p @A
  exit $LASTEXITCODE
}

$act = $Action.ToLowerInvariant()

switch ($act) {
  "help" { Print-Help; exit 0 }
  "scan" { Call-Tool "ai-scan.ps1" $Arg }
  "health" { Call-Tool "health-sample.ps1" $Arg }
  "doctor" { Call-Tool "doctor.ps1" $Arg }
  "validate" { Call-Tool "report-validate.ps1" $Arg }
  "plan" { Call-Tool "ai-plan.ps1" $Arg }
  "codex" { Call-Tool "ai-codex-review.ps1" $Arg }
  "apply" { Call-Tool "ai-apply.ps1" $Arg }
  default {
    Write-Host "Unknown action: $Action"
    Print-Help
    exit 2
  }
}
