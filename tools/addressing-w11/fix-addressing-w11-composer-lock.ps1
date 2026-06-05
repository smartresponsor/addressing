param(
    [string] $Repo = "D:\PhpstormProjects\www\Addressing"
)

$ErrorActionPreference = "Stop"
Set-Location $Repo
composer update --lock
composer validate --strict
