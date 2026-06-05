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

Remove-Item .\var\phpstan -Recurse -Force -ErrorAction SilentlyContinue

Invoke-Step "Composer validate" { composer validate --strict }
Invoke-Step "Composer autoload" { composer dump-autoload }
Invoke-Step "Container lint" { php .\bin\console lint:container --no-debug }
Invoke-Step "YAML lint" { php .\bin\console lint:yaml .\config --parse-tags }
Invoke-Step "PHPUnit" { php vendor/bin/phpunit }
Invoke-Step "PHPStan" { php vendor/bin/phpstan analyse }
Invoke-Step "Deptrac" { php vendor/bin/deptrac analyse --config-file=config/addressing_deptrac.yaml }

Write-Host "W11 RC proof passed." -ForegroundColor Green
