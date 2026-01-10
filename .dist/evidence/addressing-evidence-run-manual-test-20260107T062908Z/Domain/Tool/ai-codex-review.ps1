# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Domain = "",
    [string]$Plan = "",
    [string]$Out = ""
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path)
{
    if ( [string]::IsNullOrWhiteSpace($Path))
    {
        return
    }
    if (-not(Test-Path $Path))
    {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
    }
}

function Resolve-Domain([string]$d)
{
    if (-not [string]::IsNullOrWhiteSpace($d))
    {
        return $d
    }
    if ($env:SR_DOMAIN -and -not [string]::IsNullOrWhiteSpace($env:SR_DOMAIN))
    {
        return $env:SR_DOMAIN.Trim()
    }
    return "component"
}

$Domain = Resolve-Domain $Domain

if ( [string]::IsNullOrWhiteSpace($Plan))
{
    $Plan = ("docs/agent/{0}-ai-plan.md" -f $Domain)
}

if ( [string]::IsNullOrWhiteSpace($Out))
{
    $Out = ("docs/agent/{0}-codex-prompt.txt" -f $Domain)
}

Ensure-Dir (Split-Path -Parent $Out)

$planText = ""
if (Test-Path $Plan)
{
    $planText = Get-Content $Plan -Raw
}

$txt = @()
$txt += "You are an automated review agent for a Symfony/PHP repo."
$txt += ""
$txt += "Goal: propose a minimal change-set within allowlisted paths only."
$txt += "Allowlisted paths: Domain/, .github/workflows/, docs/."
$txt += "Do not touch business code."
$txt += ""
$txt += "Repo domain: $Domain"
$txt += ""
$txt += "Plan:"
$txt += $planText
$txt += ""
$txt += "Output format:"
$txt += "- Return a patch (unified diff) and a short PR message."
$txt += "- No TODOs or placeholders."
$txtOut = ($txt -join "`n")

$txtOut | Set-Content -Path $Out -Encoding UTF8
Write-Host "OK: $Out"
exit 0
