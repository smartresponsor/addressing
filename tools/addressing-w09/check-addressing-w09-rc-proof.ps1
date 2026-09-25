$ErrorActionPreference = "Stop"
$Repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $Repo

function Invoke-RcStep {
    param(
        [Parameter(Mandatory = $true)][string]$Title,
        [Parameter(Mandatory = $true)][scriptblock]$Command
    )

    Write-Host "== $Title ==" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Title failed with exit code $LASTEXITCODE"
    }
}

Write-Host "== Cleanup runtime cache ==" -ForegroundColor Cyan
if (Test-Path ".\var\cache") { [System.IO.Directory]::Delete((Resolve-Path ".\var\cache").Path, $true) }
if (Test-Path ".\var\phpstan") { [System.IO.Directory]::Delete((Resolve-Path ".\var\phpstan").Path, $true) }

Invoke-RcStep "Composer validate" { composer validate --strict }
Invoke-RcStep "Composer autoload" { composer dump-autoload }
Invoke-RcStep "Symfony DI/container" { php .\bin\console lint:container --no-debug }
Invoke-RcStep "YAML" { php .\bin\console lint:yaml .\config --parse-tags }
Invoke-RcStep "PHPUnit" { php vendor/bin/phpunit }
Invoke-RcStep "PHPStan" { php vendor/bin/phpstan analyse }
Invoke-RcStep "Deptrac" { php vendor/bin/deptrac analyse --config-file=config/address_deptrac.yaml }

Write-Host "W09 RC proof passed." -ForegroundColor Green
