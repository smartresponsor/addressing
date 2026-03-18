<#
Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
Author: Oleksandr Tishchenko <dev@smartresponsor.com>
Owner: Marketing America Corp

Classify current Address repo files into suggested repos:
- address-data
- address-engine
- address-locator

This script is read-only: it only writes a CSV report.
#>

    [CmdletBinding()]
param(
    [string]$Root = (Get-Location).Path,
    [string]$OutCsv = "report\address-split-classify.csv"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$path)
{
    $dir = Split-Path -Parent $path
    if (![string]::IsNullOrWhiteSpace($dir) -and !(Test-Path $dir))
    {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
}

function Classify([string]$rel)
{
    $p = $rel.Replace('\\', '/')

    # Locator surface
    if ($p -match '^src/Http/LocatorApi/' -or
            $p -match '^src/Service/Locator/' -or
            $p -match '^src/ServiceInterface/Locator/' -or
            $p -match '^tools/locator/' -or
            $p -match '^bin/locator-')
    {
        return @{ repo = 'address-locator'; reason = 'locator-surface' }
    }

    # Engine utilities
    if ($p -match '^src/Utility/AddressEngine/' -or
            $p -match '^src/Parser/' -or
            $p -match '^src/Geocode/' -or
            $p -match '^src/Normalizer/' -or
            $p -match '^src/Dto/' -or
            $p -match '^tools/(geocode|parse|index)/' -or
            $p -match '^bin/address-(geocode|parse|index)')
    {
        return @{ repo = 'address-engine'; reason = 'engine-utility' }
    }

    # Data core (preferred)
    if ($p -match '^src/Http/AddressApi/' -or
            $p -match '^src/Entity/' -or
            $p -match '^src/EntityInterface/' -or
            $p -match '^src/Repository/' -or
            $p -match '^src/RepositoryInterface/' -or
            $p -match '^src/Service/Application/Address/' -or
            $p -match '^src/ServiceInterface/Application/Address/' -or
            $p -match '^public/' -or
            $p -match '^openapi/' -or
            $p -match '^sql/' -or
            $p -match '^bin/address-')
    {
        return @{ repo = 'address-data'; reason = 'data-core' }
    }

    # Everything else: likely legacy / needs manual decision
    return @{ repo = 'legacy'; reason = 'unclassified' }
}

$rootFull = (Resolve-Path $Root).Path
Ensure-Dir (Join-Path $rootFull $OutCsv)

$files = Get-ChildItem -Path $rootFull -Recurse -File | Where-Object {
    $_.FullName -notmatch "\\.git\\" -and $_.FullName -notmatch "\\vendor\\" -and $_.FullName -notmatch "\\split\\"
}

$rows = foreach ($f in $files)
{
    $rel = $f.FullName.Substring($rootFull.Length).TrimStart('\', '/')
    $c = Classify $rel
    [pscustomobject]@{
        path = $rel
        suggestedRepo = $c.repo
        reason = $c.reason
        sizeByte = $f.Length
    }
}

$rows | Sort-Object suggestedRepo, path | Export-Csv -NoTypeInformation -Encoding UTF8 -Path (Join-Path $rootFull $OutCsv)
Write-Host "Wrote: $OutCsv"
