# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Repo = ".",
  [string]$Out = "report/address-doctor.json"
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ($null -eq $Path -or $Path.Trim() -eq "") { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Safe-Call([string]$Exe, [string[]]$Arg) {
  $cmd = Get-Command $Exe -ErrorAction SilentlyContinue
  if (-not $cmd) { return @{ ok = $false; note = "not found" } }
  try {
    $out = & $cmd.Source @Arg 2>&1
    return @{ ok = $true; note = ($out | Out-String).Trim() }
  } catch {
    return @{ ok = $false; note = $_.Exception.Message }
  }
}

function Find-CanonBin([string]$Root) {
  $candidates = @(
    (Join-Path $Root "vendor/bin/sr-canon"),
    (Join-Path $Root "vendor/bin/smartresponsor-canon"),
    (Join-Path $Root "vendor/bin/canon")
  )
  foreach ($c in $candidates) {
    if (Test-Path $c) { return $c }
  }
  return $null
}

$repoPath = Resolve-Path $Repo
Ensure-Dir (Split-Path -Parent $Out)

$domainDir = Join-Path $repoPath.Path "Domain"
$domainAiDir = Join-Path $domainDir "Ai"
$domainToolDir = Join-Path $domainDir "Tool"
$reportDir = Join-Path $repoPath.Path "report"

$issue = @()

if (-not (Test-Path $domainDir)) { $issue += "Domain overlay folder not found: Domain/" }
if (-not (Test-Path $domainAiDir)) { $issue += "Domain/Ai folder not found" }
if (-not (Test-Path $domainToolDir)) { $issue += "Domain/Tool folder not found" }

if (Test-Path (Join-Path $repoPath.Path "src\Domain")) {
  $issue += "Forbidden path found: src\Domain (must be removed)"
}

$phpInfo = Safe-Call "php" @("-v")
$composerInfo = Safe-Call "composer" @("--version")
$gitInfo = Safe-Call "git" @("--version")

$canonBin = Find-CanonBin $repoPath.Path
$canonOk = $true
if (-not $canonBin) { $canonOk = $false; $issue += "Canon CLI not found under vendor/bin (or set SR_CANON_CHECK_CMD / SR_CANON_SCAN_CMD)" }

$apiKeyReady = $false
if ($env:OPENAI_API_KEY -and $env:OPENAI_API_KEY.Trim() -ne "") { $apiKeyReady = $true }

$srModel = ""
if ($env:SR_MODEL) { $srModel = $env:SR_MODEL }

$srEffort = ""
if ($env:SR_REASONING_EFFORT) { $srEffort = $env:SR_REASONING_EFFORT }

$now = (Get-Date).ToString("o")

$tool = [ordered]@{
  php = $phpInfo
  composer = $composerInfo
  git = $gitInfo
  canon = [ordered]@{
    ok = $canonOk
    bin = $canonBin
    checkCmdOverride = [bool]($env:SR_CANON_CHECK_CMD -and $env:SR_CANON_CHECK_CMD.Trim() -ne "")
    scanCmdOverride = [bool]($env:SR_CANON_SCAN_CMD -and $env:SR_CANON_SCAN_CMD.Trim() -ne "")
  }
}

$envState = [ordered]@{
  openaiApiKey = $apiKeyReady
  srModel = $srModel
  srReasoningEffort = $srEffort
}

$repoState = [ordered]@{
  root = $repoPath.Path
  domain = [ordered]@{
    present = (Test-Path $domainDir)
    aiPresent = (Test-Path $domainAiDir)
    toolPresent = (Test-Path $domainToolDir)
  }
  reportPresent = (Test-Path $reportDir)
}

if (-not $phpInfo.ok) { $issue += "php not found (required)" }
if (-not $gitInfo.ok) { $issue += "git not found (required for apply)" }

$ok = ($issue.Count -eq 0)

$payload = [ordered]@{
  component = "address"
  generatedAt = $now
  ok = $ok
  issue = $issue
  tool = $tool
  env = $envState
  repo = $repoState
  note = @(
    "This report is repo-local and does not change application code.",
    "Use Domain/Tool/run.ps1 for a single entry point."
  )
}

($payload | ConvertTo-Json -Depth 12) | Set-Content -Path $Out -Encoding UTF8
Write-Host "OK: $Out"

if (-not $ok) { exit 2 }
exit 0
