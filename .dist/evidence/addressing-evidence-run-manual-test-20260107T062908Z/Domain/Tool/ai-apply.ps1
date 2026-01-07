# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
#
# Safe patch applier (opt-in).
# - Does not run by default.
# - Requires SR_ALLOW_APPLY=1.
# - Applies a unified diff patch using git apply.

param(
  [Parameter(Mandatory=$true)]
  [string]$Patch,
  [string]$Repo = ".",
  [string]$TestCmd = "",
  [switch]$NoCanonCheck
)

$ErrorActionPreference = "Stop"

if ($env:SR_ALLOW_APPLY -ne "1") {
  throw "Apply is disabled. Set SR_ALLOW_APPLY=1 to proceed."
}

if (-not (Test-Path $Patch)) {
  throw "Patch file not found: $Patch"
}

$git = (Get-Command git -ErrorAction SilentlyContinue)
if (-not $git) { throw "git not found in PATH" }

Push-Location $Repo
try {
  # Verify clean working tree (recommended)
  $status = & $git.Source status --porcelain
  if ($status -and $status.Trim().Length -gt 0) {
    throw "Working tree is not clean. Commit/stash changes before apply."
  }

  Write-Host "Applying patch: $Patch"
  & $git.Source apply --whitespace=nowarn --verbose $Patch

  if (-not $NoCanonCheck) {
    $canonCheck = Join-Path $PWD "Domain/Tool/canon-check.ps1"
    if (Test-Path $canonCheck) {
      Write-Host "Running canon check..."
      & $canonCheck
    } else {
      Write-Host "Canon check script not found at Domain/Tool/canon-check.ps1 (skipping)"
    }
  }

  if ($TestCmd -and $TestCmd.Trim().Length -gt 0) {
    Write-Host "Running test command: $TestCmd"
    cmd /c $TestCmd
    if ($LASTEXITCODE -ne 0) { throw "Test command failed: exit=$LASTEXITCODE" }
  }

  Write-Host "OK: patch applied"
} finally {
  Pop-Location
}
