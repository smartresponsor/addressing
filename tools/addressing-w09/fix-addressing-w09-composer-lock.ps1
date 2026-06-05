$ErrorActionPreference = "Stop"
$Repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $Repo

composer update --lock
if ($LASTEXITCODE -ne 0) {
    throw "composer update --lock failed with exit code $LASTEXITCODE"
}

composer validate --strict
if ($LASTEXITCODE -ne 0) {
    throw "composer validate --strict failed with exit code $LASTEXITCODE"
}

Write-Host "Composer lock is synchronized." -ForegroundColor Green
