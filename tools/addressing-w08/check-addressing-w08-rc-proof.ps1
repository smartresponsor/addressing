$ErrorActionPreference = "Stop"
$Repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $Repo

Write-Host "== Cleanup runtime cache ==" -ForegroundColor Cyan
if (Test-Path ".\var\cache") { Remove-Item ".\var\cache" -Recurse -Force }
if (Test-Path ".\var\phpstan") { Remove-Item ".\var\phpstan" -Recurse -Force }

Write-Host "== Composer validate ==" -ForegroundColor Cyan
composer validate --strict

Write-Host "== Composer autoload ==" -ForegroundColor Cyan
composer dump-autoload

Write-Host "== Symfony DI/container ==" -ForegroundColor Cyan
php .\bin\console lint:container --no-debug

Write-Host "== YAML ==" -ForegroundColor Cyan
php .\bin\console lint:yaml .\config --parse-tags

Write-Host "== PHPUnit ==" -ForegroundColor Cyan
php vendor/bin/phpunit

Write-Host "== PHPStan ==" -ForegroundColor Cyan
php vendor/bin/phpstan analyse

Write-Host "== Deptrac ==" -ForegroundColor Cyan
php vendor/bin/deptrac analyse --config-file=config/addressing_deptrac.yaml

Write-Host "W08 RC proof passed." -ForegroundColor Green
