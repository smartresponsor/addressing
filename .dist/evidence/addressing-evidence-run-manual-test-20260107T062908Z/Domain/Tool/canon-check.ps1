# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Repo = "."
)

$ErrorActionPreference = "Stop"

function Find-CanonBin([string]$Root)
{
    $candidates = @(
    (Join-Path $Root "vendor/bin/sr-canon"),
    (Join-Path $Root "vendor/bin/smartresponsor-canon"),
    (Join-Path $Root "vendor/bin/canon")
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

$repoPath = Resolve-Path $Repo
$bin = Find-CanonBin $repoPath.Path

if (-not$bin)
{
    if ($env:SR_CANON_CHECK_CMD -and $env:SR_CANON_CHECK_CMD.Trim() -ne "")
    {
        $cmd = $env:SR_CANON_CHECK_CMD.Replace("{repo}", $repoPath.Path)
        Write-Host "Running: $cmd"
        cmd /c $cmd | Out-Null
        exit $LASTEXITCODE
    }

    throw "Canon CLI not found. Install Canon as dev dependency or set SR_CANON_CHECK_CMD (use {repo})."
}

$php = (Get-Command php -ErrorAction SilentlyContinue)
if (-not$php)
{
    throw "php not found in PATH"
}

Push-Location $repoPath.Path
try
{
    Write-Host "Running: php $bin check"
    & $php.Source $bin check
    exit $LASTEXITCODE
}
finally
{
    Pop-Location
}
