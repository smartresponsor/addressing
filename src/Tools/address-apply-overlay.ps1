<#
Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
Author: Oleksandr Tishchenko <dev@smartresponsor.com>
Owner: Marketing America Corp
#>

param(
    [Parameter(Mandatory = $true)]
    [string]$RepoRoot,

    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

function Write-Plan([string]$Message)
{
    if ($Apply)
    {
        Write-Host "[APPLY] $Message"
    }
    else
    {
        Write-Host "[DRY]  $Message"
    }
}

function Ensure-Dir([string]$Path)
{
    if (-not(Test-Path -LiteralPath $Path))
    {
        Write-Plan "mkdir $Path"
        if ($Apply)
        {
            New-Item -ItemType Directory -Path $Path | Out-Null
        }
    }
}

function Copy-OverlayFile([string]$RelativePath)
{
    $src = Join-Path $PSScriptRoot "..\overlay\$RelativePath"
    $dst = Join-Path $RepoRoot $RelativePath
    Ensure-Dir (Split-Path -Parent $dst)
    Write-Plan "copy $RelativePath"
    if ($Apply)
    {
        Copy-Item -LiteralPath $src -Destination $dst -Force
    }
}

$RepoRoot = (Resolve-Path -LiteralPath $RepoRoot).Path

$oldEntity = Join-Path $RepoRoot 'src\Entity\Address\Address.php'
if (Test-Path -LiteralPath $oldEntity)
{
    Write-Plan "remove src\\Entity\\Address\\Address.php (replaced by AddressData.php)"
    if ($Apply)
    {
        Remove-Item -LiteralPath $oldEntity -Force
    }
}

$files = @(
'src\Entity\Address\AddressData.php',
'src\Repository\Address\AddressRepository.php',
'src\Service\Address\AddressProjection.php',
'src\Value\CountryCode.php',
'src\Value\GeoPoint.php',
'src\Value\PostalCode.php',
'src\Value\StreetLine.php',
'src\Value\Subdivision.php'
)

foreach ($f in $files)
{
    Copy-OverlayFile $f
}

Write-Host ''
Write-Host 'Done.'
if (-not$Apply)
{
    Write-Host 'Run again with -Apply to perform changes.'
}
