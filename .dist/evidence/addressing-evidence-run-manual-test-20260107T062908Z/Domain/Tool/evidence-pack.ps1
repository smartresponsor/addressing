# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [Parameter(Mandatory = $true)]
  [string]$Domain,
  [string]$Kind = "",
  [string]$Ref = "",
  [string]$Train = "",
  [string]$OutDir = ".dist/evidence"
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ([string]::IsNullOrWhiteSpace($Path)) { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Sha256File([string]$Path) {
  $h = Get-FileHash -Algorithm SHA256 -Path $Path
  return $h.Hash.ToLowerInvariant()
}

$repo = Resolve-Path -Path "." -ErrorAction Stop
$repo = $repo.Path

$ts = (Get-Date).ToUniversalTime().ToString("yyyyMMddTHHmmssZ")
Ensure-Dir $OutDir

function Safe-Id([string]$Value) {
  if ([string]::IsNullOrWhiteSpace($Value)) { return "" }
  $x = $Value.ToLowerInvariant()
  $x = [Regex]::Replace($x, "[^a-z0-9]+", "-")
  $x = [Regex]::Replace($x, "-{2,}", "-")
  $x = $x.Trim("-")
  if ($x.Length -gt 64) { $x = $x.Substring(0, 64) }
  return $x
}

$kindFinal = $Kind
if ([string]::IsNullOrWhiteSpace($kindFinal)) {
  if ($env:GITHUB_REF_TYPE -and $env:GITHUB_REF_TYPE.ToLowerInvariant() -eq "tag") { $kindFinal = "tag" }
  elseif ($env:GITHUB_EVENT_NAME -and $env:GITHUB_EVENT_NAME -like "pull_request*") { $kindFinal = "pr" }
  else { $kindFinal = "run" }
}

$refFinal = $Ref
if ([string]::IsNullOrWhiteSpace($refFinal)) {
  if ($kindFinal -eq "tag" -and $env:GITHUB_REF_NAME) { $refFinal = $env:GITHUB_REF_NAME }
  elseif ($kindFinal -eq "pr" -and $env:SR_PR_NUMBER) { $refFinal = $env:SR_PR_NUMBER }
  elseif ($env:GITHUB_RUN_ID) { $refFinal = $env:GITHUB_RUN_ID }
}

$commitHint = ""
if ($env:GITHUB_SHA) { $commitHint = $env:GITHUB_SHA }
if ([string]::IsNullOrWhiteSpace($refFinal)) { $refFinal = $commitHint }
if ([string]::IsNullOrWhiteSpace($refFinal)) { $refFinal = "unknown" }

$id = Safe-Id $refFinal
if ([string]::IsNullOrWhiteSpace($id)) { $id = "unknown" }

$tmp = Join-Path $OutDir ("{0}-evidence-{1}-{2}-{3}" -f $Domain, $kindFinal, $id, $ts)
Ensure-Dir $tmp

# Collect inputs
$include = @(
  "report",
  "Domain/Tool",
  ".github/workflows",
  "docs"
)

foreach ($i in $include) {
  $p = Join-Path $repo $i
  if (Test-Path $p) {
    Copy-Item -Recurse -Force -Path $p -Destination (Join-Path $tmp $i)
  }
}

# Git info (best-effort)
$git = Get-Command git -ErrorAction SilentlyContinue
$commit = ""
if ($env:GITHUB_SHA) { $commit = $env:GITHUB_SHA }
if ($git -and (-not $commit)) {
  try { $commit = (& $git.Source -C $repo rev-parse HEAD 2>$null).Trim() } catch {}
}
$meta = @()
$meta += ("domain: {0}" -f $Domain)
$meta += ("kind: {0}" -f $kindFinal)
$meta += ("ref: {0}" -f $refFinal)
$meta += ("train: {0}" -f $Train)
$meta += ("tsUtc: {0}" -f (Get-Date).ToUniversalTime().ToString("o"))
$meta += ("commit: {0}" -f $commit)
($meta -join "`n") | Set-Content -Path (Join-Path $tmp "EVIDENCE.txt") -Encoding UTF8

# Manifest + checksums
$manifest = @()
$manifest += "# Evidence manifest"
$manifest += ""
$manifest += ("domain: {0}" -f $Domain)
$manifest += ("kind: {0}" -f $kindFinal)
$manifest += ("ref: {0}" -f $refFinal)
$manifest += ("train: {0}" -f $Train)
$manifest += ("timestampUtc: {0}" -f (Get-Date).ToUniversalTime().ToString("o"))
$manifest += ("commit: {0}" -f $commit)
$manifest += ""
$manifest += "files:"
$checksums = @()

$allFiles = Get-ChildItem -Recurse -File -Path $tmp | Sort-Object FullName
foreach ($f in $allFiles) {
  $rel = $f.FullName.Substring($tmp.Length).TrimStart([IO.Path]::DirectorySeparatorChar, [IO.Path]::AltDirectorySeparatorChar).Replace("\","/")
  $hash = Sha256File $f.FullName
  $manifest += ("- {0}  sha256:{1}" -f $rel, $hash)
  $checksums += ("{0}  {1}" -f $hash, $rel)
}

($manifest -join "`n") | Set-Content -Path (Join-Path $tmp "MANIFEST.md") -Encoding UTF8
($checksums -join "`n") | Set-Content -Path (Join-Path $tmp "SHA256SUMS") -Encoding UTF8

# Zip
$zip = Join-Path $OutDir ("{0}-evidence-{1}-{2}-{3}.zip" -f $Domain, $kindFinal, $id, $ts)
if (Test-Path $zip) { Remove-Item -Force $zip }
Compress-Archive -Path (Join-Path $tmp "*") -DestinationPath $zip -Force

# Output for GitHub Actions
if ($env:GITHUB_OUTPUT) {
  ("evidence_zip={0}" -f $zip) | Out-File -FilePath $env:GITHUB_OUTPUT -Append -Encoding utf8
}

Write-Host "OK: $zip"
exit 0
