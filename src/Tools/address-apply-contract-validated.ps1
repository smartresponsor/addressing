Param(
  [switch]$Apply,
  [string]$Root = (Get-Location).Path
)

$ErrorActionPreference = 'Stop'

function Copy-OverlayFile([string]$From, [string]$To, [switch]$DoIt) {
  $dir = Split-Path -Parent $To
  if (-not (Test-Path $dir)) {
    if ($DoIt) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
    else { Write-Host "MKDIR $dir" }
  }
  if ($DoIt) { Copy-Item -Force $From $To }
  else { Write-Host "COPY  $From -> $To" }
}

$overlay = Join-Path $PSScriptRoot '..\overlay'
if (-not (Test-Path $overlay)) { throw "overlay directory not found: $overlay" }

$files = Get-ChildItem -Path $overlay -Recurse -File
foreach ($f in $files) {
  $rel = $f.FullName.Substring($overlay.Length).TrimStart('\\','/')
  $dest = Join-Path $Root $rel
  Copy-OverlayFile -From $f.FullName -To $dest -DoIt:$Apply
}

if (-not $Apply) {
  Write-Host 'Dry-run only. Re-run with -Apply to write changes.'
}
