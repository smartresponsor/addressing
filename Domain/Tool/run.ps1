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
  .\Domain\Tool\run.ps1 pr       [-Base master]
  .\Domain\Tool\run.ps1 apply    -Patch <file.patch> [-Repo .] [-TestCmd "<cmd>"] [-NoCanonCheck]

Notes:
- All commands are repo-local and should not modify application code unless you run "apply".
- "apply" requires SR_ALLOW_APPLY=1.
"@
  Write-Host $txt
}

function Resolve-ToolDir {
  $scriptFile = $null

  if ($MyInvocation -and $MyInvocation.MyCommand -and $MyInvocation.MyCommand.Path -and $MyInvocation.MyCommand.Path.Trim() -ne "") {
    $scriptFile = $MyInvocation.MyCommand.Path
  } elseif ($PSCommandPath -and $PSCommandPath.Trim() -ne "") {
    $scriptFile = $PSCommandPath
  }

  if ($scriptFile -and $scriptFile.Trim() -ne "") {
    $d = Split-Path -Parent $scriptFile
    if ($d -and $d.Trim() -ne "") { return $d }
  }

  return (Resolve-Path "Domain/Tool").Path
}

$ToolDir = Resolve-ToolDir
if (-not $ToolDir -or $ToolDir.Trim() -eq "") { throw "ToolDir is empty (cannot resolve Domain/Tool)." }

$RepoRoot = (Resolve-Path (Join-Path $ToolDir "../..")).Path
Set-Location $RepoRoot

function Call-Tool([string]$Name, [string[]]$A) {
  if (-not $ToolDir -or $ToolDir.Trim() -eq "") { throw "ToolDir is empty (cannot resolve Domain/Tool)." }
  $p = Join-Path $ToolDir $Name
  if (-not (Test-Path $p)) { throw "Tool not found: Domain/Tool/$Name" }

  pwsh -NoProfile -File $p @A
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
  "pr" { Call-Tool "agent-pr.ps1" $Arg }
  "apply" { Call-Tool "ai-apply.ps1" $Arg }
  default {
    Write-Host "Unknown action: $Action"
    Print-Help
    exit 2
  }
}
