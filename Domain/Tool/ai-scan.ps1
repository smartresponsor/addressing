# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Domain = "",
    [string]$Out = ""
)

$ErrorActionPreference = "Stop"

. "$PSScriptRoot/lib/common.ps1"

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

        $proc = Start-Process `
      -FilePath "cmd.exe" `
      -ArgumentList @("/c", $cmd) `
      -NoNewWindow `
      -Wait `
      -PassThru

        if ($proc.ExitCode -ne 0)
        {
            throw "Canon scan command failed (exit $( $proc.ExitCode )): $cmd"
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
        hint = "Set SR_CANON_SCAN_CMD (use {out}) or add Canon as dev dependency."
        tsUtc = (Get-Date).ToUniversalTime().ToString("o")
    }

    ($skipped | ConvertTo-Json -Depth 10) |
            Set-Content -Path $Out -Encoding UTF8

    Write-Host "OK: $Out"
    exit 0
}

Write-Host "Running canon scan via: $bin"

& $bin scan `
  --domain $Domain `
  --out $Out

if (-not(Test-Path $Out))
{
    throw "Canon scan finished but output not found: $Out"
}

Write-Host "OK: $Out"
