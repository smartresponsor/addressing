# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

[CmdletBinding()]
param(
  [string]$WorkerPath = "Domain/Ai/agent-trigger/worker",
  [string]$WranglerVersion = "4.57.0"
)

$ErrorActionPreference = "Stop"

function Try-Get-GitTopLevel([string]$StartPath) {
  try {
    $top = & git -C $StartPath rev-parse --show-toplevel 2>$null
    if ($LASTEXITCODE -eq 0 -and $top) { return $top.Trim() }
  } catch { }
  return $null
}

$repoRoot = Try-Get-GitTopLevel -StartPath "."
if (-not $repoRoot) { $repoRoot = (Resolve-Path -LiteralPath ".").Path }

$wk = Join-Path $repoRoot $WorkerPath
if (-not (Test-Path -LiteralPath $wk)) {
  throw "Worker folder not found: $wk"
}

Push-Location $wk
try {
  if (-not (Test-Path -LiteralPath "wrangler.toml")) {
    throw "wrangler.toml not found in worker folder: $wk"
  }

  Write-Host "Deploying Worker from: $wk"
  Write-Host "Using wrangler@$WranglerVersion"

  # Use npx so repo does not need global wrangler installation.
  & npx -y "wrangler@$WranglerVersion" deploy
  if ($LASTEXITCODE -ne 0) { throw "wrangler deploy failed" }
} finally {
  Pop-Location
}
