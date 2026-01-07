# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [Parameter(Mandatory = $true)]
  [string]$Domain,
  [string]$Sketch = "",
  [string]$Owner = "",
  [string]$Repo = "",
  [switch]$Force
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ([string]::IsNullOrWhiteSpace($Path)) { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Read-Text([string]$Path) {
  return Get-Content -Path $Path -Raw -ErrorAction Stop
}

function Write-Text([string]$Path, [string]$Content) {
  Ensure-Dir (Split-Path -Parent $Path)
  Set-Content -Path $Path -Value $Content -Encoding UTF8
}

$repoRoot = Resolve-Path -Path "." -ErrorAction Stop
$repoRoot = $repoRoot.Path

$tplRoot = Join-Path $repoRoot "tool/template"
if (-not (Test-Path $tplRoot)) { throw "Missing tool/template in current repo. Copy the kit files first." }

$wfTpl = Join-Path $tplRoot "github-workflow"
$wkTpl = Join-Path $tplRoot "worker"

# Workflows
$wfOut = Join-Path $repoRoot ".github/workflows"
Ensure-Dir $wfOut

$wfFiles = @(
  @{ src = "gate.yml"; dst = ("{0}-gate.yml" -f $Domain) },
  @{ src = "evidence-release.yml"; dst = ("{0}-evidence-release.yml" -f $Domain) },
  @{ src = "agent-dispatch.yml"; dst = ("{0}-agent-dispatch.yml" -f $Domain) },
  @{ src = "agent-pr.yml"; dst = ("{0}-agent-pr.yml" -f $Domain) }
)

foreach ($m in $wfFiles) {
  $src = Join-Path $wfTpl $m.src
  if (-not (Test-Path $src)) { throw "Missing template: $src" }
  $dst = Join-Path $wfOut $m.dst

  if ((Test-Path $dst) -and (-not $Force.IsPresent)) {
    Write-Host "SKIP: $dst exists (use -Force to overwrite)"
    continue
  }

  $c = Read-Text $src
  $c = $c.Replace("__DOMAIN__", $Domain)

  Write-Text $dst $c
  Write-Host "OK: $dst"
}

# Worker template (optional copy)
if (Test-Path $wkTpl) {
  $wkOut = Join-Path $repoRoot "Domain/Ai/agent-trigger/worker"
  Ensure-Dir $wkOut

  $ownerFinal = $Owner
  if ([string]::IsNullOrWhiteSpace($ownerFinal)) { $ownerFinal = "__OWNER__" }
  $repoFinal = $Repo
  if ([string]::IsNullOrWhiteSpace($repoFinal)) { $repoFinal = "__REPO__" }

  $wr = Read-Text (Join-Path $wkTpl "wrangler.toml")
  $wr = $wr.Replace("__DOMAIN__", $Domain).Replace("__OWNER__", $ownerFinal).Replace("__REPO__", $repoFinal)
  Write-Text (Join-Path $wkOut "wrangler.toml") $wr

  Copy-Item -Recurse -Force -Path (Join-Path $wkTpl "src") -Destination (Join-Path $wkOut "src")
  Copy-Item -Force -Path (Join-Path $wkTpl "package.json") -Destination (Join-Path $wkOut "package.json")

  # Replace placeholders inside worker files
  $wkFiles = Get-ChildItem -Recurse -File -Path $wkOut
  foreach ($f in $wkFiles) {
    try {
      $txt = Get-Content -Path $f.FullName -Raw -ErrorAction Stop
      $txt = $txt.Replace("__DOMAIN__", $Domain).Replace("__OWNER__", $ownerFinal).Replace("__REPO__", $repoFinal)
      Set-Content -Path $f.FullName -Value $txt -Encoding UTF8
    } catch { }
  }

  Write-Host "OK: Worker template copied to $wkOut"
}

exit 0
