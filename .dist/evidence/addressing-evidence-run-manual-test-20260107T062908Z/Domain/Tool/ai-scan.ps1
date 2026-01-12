# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Domain = "",
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

function Find-CanonBin
{
    $candidates = @(
    "vendor/bin/canon"
    )
    foreach ($c in $candidates)
    {
        if (Test-Path $c)
        {
            return $c
        }
    }
    return $null
}

$Domain = Resolve-Domain $Domain

if ( [string]::IsNullOrWhiteSpace($Out))
{
    $Out = ("report/{0}-canon-scan.json" -f $Domain)
}

Ensure-Dir (Split-Path -Parent $Out)

$bin = Find-CanonBin
if (-not$bin)
{
    if ($env:SR_CANON_SCAN_CMD -and $env:SR_CANON_SCAN_CMD.Trim() -ne "")
    {
        $cmd = $env:SR_CANON_SCAN_CMD.Replace("{out}", $Out)
        Write-Host "Running: $cmd"
        cmd /c $cmd | Out-Null
        if (-not(Test-Path $Out))
        {
            throw "Canon scan finished but output not found: $Out"
        }
        Write-Host "OK: $Out"
        exit 0
    }

    $required = ($env:SR_CANON_REQUIRED -eq "1")
    if ($required)
    {
        throw "Canon CLI not found. Install Canon or set SR_CANON_SCAN_CMD (use {out})."
    }

    Write-Host "WARN: Canon CLI not found. Writing skipped scan report."

    $skipped = [ordered]@{
        ok = $true
        status = "skipped"
        reason = "canon_cli_missing"
        hint = "Set SR_CANON_SCAN_CMD (use {out}) or add Canon as dev dependency."
        tsUtc = (Get-Date).ToUniversalTime().ToString("o")
    }

    ($skipped | ConvertTo-Json -Depth 10) | Set-Content -Path $Out -Encoding UTF8
    Write-Host "OK: $Out"
    exit 0
}

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not$php)
{
    throw "php not found in PATH"
}

Write-Host "Running: php $bin scan --format json --out $Out"
& $php.Source $bin scan --format json --out $Out

if (-not(Test-Path $Out))
{
    throw "Output not found: $Out"
}
Write-Host "OK: $Out"
exit 0
