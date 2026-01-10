# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Domain = "",
    [string]$Repo = ".",
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

$repoPath = Resolve-Path -Path $Repo -ErrorAction Stop
$repoPath = $repoPath.Path

if ( [string]::IsNullOrWhiteSpace($Out))
{
    $Out = Join-Path $repoPath ("report/{0}-doctor.json" -f $Domain)
}

Ensure-Dir (Split-Path -Parent $Out)

$git = Get-Command git -ErrorAction SilentlyContinue

$findings = @()

function Add-Finding([string]$level, [string]$code, [string]$message, [string]$path = "")
{
    $findings += [ordered]@{
        level = $level
        code = $code
        message = $message
        path = $path
    }
}

# Minimal structural checks (non-fatal by default)
if (-not(Test-Path (Join-Path $repoPath ".github")))
{
    Add-Finding "warn" "missing_github_dir" ".github directory is missing." ".github"
}
if (Test-Path (Join-Path $repoPath "src/Domain"))
{
    Add-Finding "warn" "legacy_src_domain" "src/Domain exists. Consider layer-first isolation for production repos." "src/Domain"
}
if (-not(Test-Path (Join-Path $repoPath "Domain/Tool/run.ps1")))
{
    Add-Finding "error" "missing_domain_tool" "Domain/Tool/run.ps1 is missing." "Domain/Tool/run.ps1"
}

$commit = ""
$branch = ""
if ($git)
{
    try
    {
        $commit = (& $git.Source -C $repoPath rev-parse HEAD 2> $null).Trim()
    }
    catch
    {
    }
    try
    {
        $branch = (& $git.Source -C $repoPath rev-parse --abbrev-ref HEAD 2> $null).Trim()
    }
    catch
    {
    }
}

$ok = $true
foreach ($f in $findings)
{
    if ($f.level -eq "error")
    {
        $ok = $false
    }
}

$report = [ordered]@{
    ok = $ok
    domain = $Domain
    repo = $repoPath
    branch = $branch
    commit = $commit
    finding = $findings
    tsUtc = (Get-Date).ToUniversalTime().ToString("o")
}

($report | ConvertTo-Json -Depth 10) | Set-Content -Path $Out -Encoding UTF8
Write-Host "OK: $Out"
if (-not$ok)
{
    exit 2
}
exit 0
