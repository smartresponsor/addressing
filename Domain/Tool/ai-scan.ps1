# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Out = "report/address-canon-scan.json"
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path)
{
    if ($null -eq $Path -or $Path.Trim() -eq "")
    {
        return
    }
    if (-not(Test-Path $Path))
    {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
    }
}

function Find-CanonBin
{
    $candidates = @(
    "vendor/bin/sr-canon",
    "vendor/bin/smartresponsor-canon",
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

Ensure-Dir (Split-Path -Parent $Out)

$bin = Find-CanonBin
if (-not$bin)
{
    if ($env:SR_CANON_SCAN_CMD -and $env:SR_CANON_SCAN_CMD.Trim() -ne "")
    {
        $cmd = $env:SR_CANON_SCAN_CMD.Replace("{out}", ('"' + $Out + '"'))
        Write-Host "Running: $cmd"

        & ([ScriptBlock]::Create($cmd)) | Out-Null
        if ($LASTEXITCODE -ne 0)
        {
            throw "Canon scan failed (exit=$LASTEXITCODE)."
        }

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
        hint = "Set SR_CANON_SCAN_CMD (use {out}) or provide Domain/Canon runner."
        tsUtc = (Get-Date).ToUniversalTime().ToString("o")
    }

    $dir = Split-Path -Parent $Out
    if ($dir -and -not(Test-Path $dir))
    {
        New-Item -ItemType Directory -Path $dir | Out-Null
    }

    ($skipped | ConvertTo-Json -Depth 10) | Set-Content -Path $Out -Encoding UTF8
    Write-Host "OK: $Out"
    exit 0
}

$php = (Get-Command php -ErrorAction SilentlyContinue)
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
