param(
    [string] $Repo = "D:\PhpstormProjects\www\Addressing"
)

$ErrorActionPreference = "Stop"
Set-Location $Repo

function Invoke-Step {
    param(
        [string] $Title,
        [scriptblock] $Command
    )

    Write-Host "== $Title ==" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Title failed with exit code $LASTEXITCODE"
    }
}

if (Test-Path ".\var\phpstan") { [System.IO.Directory]::Delete((Resolve-Path ".\var\phpstan").Path, $true) }

Invoke-Step "Composer validate" { composer validate --strict }
Invoke-Step "Composer autoload" { composer dump-autoload }
Invoke-Step "Container lint" { php .\bin\console lint:container --no-debug }
Invoke-Step "YAML lint" { php .\bin\console lint:yaml .\config --parse-tags }
Invoke-Step "PHPUnit" { php vendor/bin/phpunit }
Invoke-Step "PHPStan" { php vendor/bin/phpstan analyse }
Invoke-Step "Deptrac" { php vendor/bin/deptrac analyse --config-file=config/address_deptrac.yaml }

Write-Host "W11 RC proof passed." -ForegroundColor Green
