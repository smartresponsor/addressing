# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$Action,
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Arg
)

$ErrorActionPreference = "Stop"

function Print-Help
{
    $txt = @"
Domain tool runner

Usage:
  pwsh -NoProfile -File Domain/Tool/run.ps1 help
  pwsh -NoProfile -File Domain/Tool/run.ps1 doctor   [-Domain <name>] [-Repo <path>] [-Out <file.json>]
  pwsh -NoProfile -File Domain/Tool/run.ps1 scan     [-Domain <name>] [-Out <file.json>]
  pwsh -NoProfile -File Domain/Tool/run.ps1 health   [-Domain <name>] [-Repo <path>] [-Out <file.json>]
  pwsh -NoProfile -File Domain/Tool/run.ps1 validate [-Domain <name>] [-Repo <path>] [-Out <file.json>] [-AllowMissing]
  pwsh -NoProfile -File Domain/Tool/run.ps1 plan     [-Domain <name>] [-Scan <scan.json>] [-Out <plan.md>]
  pwsh -NoProfile -File Domain/Tool/run.ps1 codex    [-Domain <name>] [-Plan <plan.md>] [-Out <prompt.txt>]

Notes:
- All commands are repo-local and should not modify application code.
- Canon scan is optional by default. Set SR_CANON_REQUIRED=1 to enforce.
"@
    Write-Host $txt
}

function Resolve-ToolDir
{
    $d = $PSScriptRoot
    if ( [string]::IsNullOrWhiteSpace($d))
    {
        if (-not [string]::IsNullOrWhiteSpace($PSCommandPath))
        {
            $d = Split-Path -Parent $PSCommandPath
        }
    }
    if ( [string]::IsNullOrWhiteSpace($d))
    {
        if ($MyInvocation -and $MyInvocation.MyCommand -and $MyInvocation.MyCommand.Path)
        {
            $d = Split-Path -Parent $MyInvocation.MyCommand.Path
        }
    }
    if ( [string]::IsNullOrWhiteSpace($d))
    {
        $d = Join-Path (Get-Location) "Domain/Tool"
    }
    if ( [string]::IsNullOrWhiteSpace($d))
    {
        throw "Cannot resolve Domain/Tool directory path."
    }
    return $d
}

$ToolDir = Resolve-ToolDir

function Call-Tool([string]$Name, [string[]]$A)
{
    $p = Join-Path $ToolDir $Name
    if (-not(Test-Path $p))
    {
        throw "Tool not found: Domain/Tool/$Name"
    }
    & pwsh -NoProfile -File $p @A
    exit $LASTEXITCODE
}

$act = $Action.ToLowerInvariant()

switch ($act)
{
    "help" {
        Print-Help; exit 0
    }
    "doctor" {
        Call-Tool "doctor.ps1" $Arg
    }
    "scan" {
        Call-Tool "ai-scan.ps1" $Arg
    }
    "health" {
        Call-Tool "health-sample.ps1" $Arg
    }
    "validate" {
        Call-Tool "report-validate.ps1" $Arg
    }
    "plan" {
        Call-Tool "ai-plan.ps1" $Arg
    }
    "codex" {
        Call-Tool "ai-codex-review.ps1" $Arg
    }
    default {
        Write-Host "Unknown action: $Action"
        Print-Help
        exit 2
    }
}
