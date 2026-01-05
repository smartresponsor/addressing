<#
Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
Author: Oleksandr Tishchenko <dev@smartresponsor.com>
Owner: Marketing America Corp

Split the current Address repo into three folder trees under ./split:
- address-data
- address-engine
- address-locator

By default this is non-destructive (copy only).
Use -Purge to delete the original files after copying.
#>

[CmdletBinding()]
param(
  [string]$Root = (Get-Location).Path,
  [string]$SplitDir = "split",
  [switch]$Purge
)

function Get-SuggestedRepo([string]$RelativePath) {
  $p = $RelativePath -replace "\\\\", "/"

  if ($p -match "^src/Http/AddressApi/" -or $p -match "^src/Service/Address/" -or $p -match "^src/Repository/Address/" -or $p -match "^src/Entity/Address/" -or $p -match "^src/EntityInterface/Address/" -or $p -match "^src/RepositoryInterface/Address/" -or $p -match "^src/ServiceInterface/Address/") {
    return "address-data"
  }

  if ($p -match "^src/Http/LocatorApi/" -or $p -match "^src/Service/Locator/" -or $p -match "^src/ServiceInterface/Locator/" -or $p -match "^src/Utility/Locator/") {
    return "address-locator"
  }

  if ($p -match "^src/Utility/AddressEngine/" -or $p -match "^src/(Geocode|Parser|Normalizer|Dto|DTO)/" -or $p -match "^tools/(geocode|parse|index)/" -or $p -match "^bin/address-(geocode|parse|index)") {
    return "address-engine"
  }

  if ($p -match "^public/" -or $p -match "^openapi/" -or $p -match "^sql/" -or $p -match "^bin/" -or $p -match "^tools/" -or $p -match "^docs/") {
    return "address-data"
  }

  if ($p -match "^src/Projection/AddressIndex/") {
    return "address-engine"
  }

  if ($p -match "^src/" ) {
    return "legacy"
  }

  return "address-data"
}

$rootPath = (Resolve-Path -LiteralPath $Root).Path
$splitPath = Join-Path $rootPath $SplitDir
New-Item -ItemType Directory -Force -Path $splitPath | Out-Null

$excludeDir = @(
  (Join-Path $rootPath ".git"),
  (Join-Path $rootPath "vendor"),
  (Join-Path $rootPath $SplitDir)
)

$files = Get-ChildItem -LiteralPath $rootPath -Recurse -File | Where-Object {
  $full = $_.FullName
  foreach ($ex in $excludeDir) {
    if ($full.StartsWith($ex)) { return $false }
  }
  return $true
}

$copied = 0
$purged = 0

foreach ($f in $files) {
  $rel = $f.FullName.Substring($rootPath.Length).TrimStart("\\", "/")
  $repo = Get-SuggestedRepo $rel
  $dstRoot = Join-Path $splitPath $repo
  $dstFile = Join-Path $dstRoot $rel
  $dstDir = Split-Path -Parent $dstFile
  New-Item -ItemType Directory -Force -Path $dstDir | Out-Null
  Copy-Item -LiteralPath $f.FullName -Destination $dstFile -Force
  $copied++

  if ($Purge) {
    Remove-Item -LiteralPath $f.FullName -Force
    $purged++
  }
}

Write-Host ("Copied {0} files into {1}." -f $copied, $splitPath)
if ($Purge) {
  Write-Host ("Purged {0} original files." -f $purged)
}

Write-Host "Output folders: split/address-data, split/address-engine, split/address-locator (plus split/legacy)."
