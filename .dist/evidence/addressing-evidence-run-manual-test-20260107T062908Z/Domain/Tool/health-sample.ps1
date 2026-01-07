# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Domain = "",
  [string]$Repo = ".",
  [string]$Out = ""
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ([string]::IsNullOrWhiteSpace($Path)) { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Resolve-Domain([string]$d) {
  if (-not [string]::IsNullOrWhiteSpace($d)) { return $d }
  if ($env:SR_DOMAIN -and -not [string]::IsNullOrWhiteSpace($env:SR_DOMAIN)) { return $env:SR_DOMAIN.Trim() }
  return "component"
}

$Domain = Resolve-Domain $Domain
$repoPath = Resolve-Path -Path $Repo -ErrorAction Stop
$repoPath = $repoPath.Path

if ([string]::IsNullOrWhiteSpace($Out)) {
  $Out = Join-Path $repoPath ("report/{0}-health-sample.json" -f $Domain)
}

Ensure-Dir (Split-Path -Parent $Out)

$payload = [ordered]@{
  ok = $true
  service = ("{0}-agent-trigger" -f $Domain)
  hint = "This is a sample payload. Replace with real health endpoint checks when app is present."
  tsUtc = (Get-Date).ToUniversalTime().ToString("o")
}

($payload | ConvertTo-Json -Depth 10) | Set-Content -Path $Out -Encoding UTF8
Write-Host "OK: $Out"
exit 0
