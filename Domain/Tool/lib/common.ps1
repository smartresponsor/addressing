# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

function Ensure-Dir([string]$Path) {
  if ([string]::IsNullOrWhiteSpace($Path)) { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Resolve-Domain([string]$d) {
  if (-not [string]::IsNullOrWhiteSpace($d)) { return $d }
  if ($env:SR_DOMAIN -and -not [string]::IsNullOrWhiteSpace($env:SR_DOMAIN)) { return $env:SR_DOMAIN.Trim() }
  return "component"
}
