# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Domain = "",
  [string]$Repo = ".",
  [string]$Out = "",
  [switch]$AllowMissing
)

$ErrorActionPreference = "Stop"

. "$PSScriptRoot/lib/common.ps1"

$Domain = Resolve-Domain $Domain
$repoPath = Resolve-Path -Path $Repo -ErrorAction Stop
$repoPath = $repoPath.Path

if ([string]::IsNullOrWhiteSpace($Out)) {
  $Out = Join-Path $repoPath ("report/{0}-report-validate.json" -f $Domain)
}

Ensure-Dir (Split-Path -Parent $Out)

$reportDir = Join-Path $repoPath "report"
if (-not (Test-Path $reportDir)) {
  if ($AllowMissing.IsPresent) {
    $ok = [ordered]@{
      ok = $true
      status = "skipped"
      reason = "report_dir_missing"
      tsUtc = (Get-Date).ToUniversalTime().ToString("o")
    }
    ($ok | ConvertTo-Json -Depth 10) | Set-Content -Path $Out -Encoding UTF8
    Write-Host "OK: $Out"
    exit 0
  }
  throw "report directory not found: $reportDir"
}

$files = Get-ChildItem -Path $reportDir -Filter "*.json" -File -ErrorAction SilentlyContinue |
  Sort-Object -Property FullName
$bad = @()
foreach ($f in $files) {
  try {
    $null = Get-Content $f.FullName -Raw -ErrorAction Stop | ConvertFrom-Json -ErrorAction Stop
  } catch {
    $bad += $f.FullName
  }
}

$okFlag = ($bad.Count -eq 0)
$outObj = [ordered]@{
  ok = $okFlag
  domain = $Domain
  jsonCount = $files.Count
  invalidJson = ($bad | Sort-Object)
  tsUtc = (Get-Date).ToUniversalTime().ToString("o")
}

($outObj | ConvertTo-Json -Depth 10) | Set-Content -Path $Out -Encoding UTF8
Write-Host "OK: $Out"
if (-not $okFlag) { exit 2 }
exit 0
