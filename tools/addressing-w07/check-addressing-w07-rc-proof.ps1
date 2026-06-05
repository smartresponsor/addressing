$Repo = "D:\PhpstormProjects\www\Addressing"
Set-Location $Repo
$ErrorActionPreference = "Stop"

composer validate --strict
composer dump-autoload
php .\bin\console lint:container --no-debug
php .\bin\console lint:yaml .\config --parse-tags
php vendor/bin/phpunit
php vendor/bin/phpstan analyse
php vendor/bin/deptrac analyse --config-file=config/addressing_deptrac.yaml

$Hits = Select-String -Path .\src\*.php,.\src\**\*.php,.\config\*.yaml,.\tests\*.php,.\tests\**\*.php,.\bin\*.php,.\tools\**\*.php,.\public\*.php,.\composer.json `
    -Pattern "AddressRepositoryInterface|AddressService|AddressHttpService|App\\Bridge|App\\Infrastructure|App\\Fixture|App\\Integration\\Console\\Command|category-" `
    -ErrorAction SilentlyContinue

if ($Hits) {
    $Hits | ForEach-Object { Write-Host "$($_.Path):$($_.LineNumber): $($_.Line)" -ForegroundColor Red }
    throw "Legacy/canon grep failed"
}

Write-Host "RC proof commands passed." -ForegroundColor Green
