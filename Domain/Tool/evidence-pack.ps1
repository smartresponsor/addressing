# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Domain = "address",
  [string]$Sketch = "32",
  [string]$OutDir = "report/evidence",
  [string]$RefName = ""
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ($null -eq $Path -or $Path.Trim() -eq "") { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Write-File([string]$Path, [string]$Content) {
  Ensure-Dir (Split-Path -Parent $Path)
  $Content | Set-Content -Path $Path -Encoding UTF8
}

function Sha256([string]$Path) {
  return (Get-FileHash -Algorithm SHA256 -Path $Path).Hash.ToLowerInvariant()
}

Ensure-Dir $OutDir

$git = (Get-Command git -ErrorAction SilentlyContinue)
if (-not $git) { throw "git not found in PATH" }

$shaFull = (& git rev-parse HEAD).Trim()
$sha = (& git rev-parse --short=12 HEAD).Trim()

$tag = ""
if ($env:GITHUB_REF_TYPE -eq "tag" -and $env:GITHUB_REF_NAME) { $tag = $env:GITHUB_REF_NAME }

if ($RefName -and $RefName.Trim() -ne "") {
  $ref = $RefName.Trim()
} elseif ($tag -and $tag.Trim() -ne "") {
  $ref = $tag.Trim()
} else {
  $ref = "head-$sha"
}

$runId = ""
if ($env:GITHUB_RUN_ID) { $runId = $env:GITHUB_RUN_ID.Trim() }

$ts = (Get-Date).ToUniversalTime().ToString("yyyyMMddTHHmmssZ")

$base = "$Domain-sketch$Sketch-evidence-$ref-$ts"
if ($runId -and $runId -ne "") { $base = "$base-$runId" }

$tmp = Join-Path $OutDir "_tmp"
if (Test-Path $tmp) { Remove-Item -Recurse -Force $tmp }
New-Item -ItemType Directory -Force -Path $tmp | Out-Null

# 1) Source snapshot (deterministic: git archive)
$srcZip = Join-Path $tmp "source.zip"
Write-Host "Create source archive: $srcZip"
& git archive --format=zip --output="$srcZip" HEAD

# 2) Copy reports if exist
$reportDir = Join-Path (Get-Location) "report"
if (Test-Path $reportDir) {
  $dstReport = Join-Path $tmp "report"
  New-Item -ItemType Directory -Force -Path $dstReport | Out-Null
  Get-ChildItem -Path $reportDir -File -Recurse -ErrorAction SilentlyContinue | ForEach-Object {
    $rel = $_.FullName.Substring($reportDir.Length).TrimStart('\','/')
    $to = Join-Path $dstReport $rel
    Ensure-Dir (Split-Path -Parent $to)
    Copy-Item -Force $_.FullName $to
  }
}

# 3) Manifest + sums
$meta = [ordered]@{
  ok = $true
  domain = $Domain
  sketch = $Sketch
  ref = $ref
  sha = $shaFull
  shaShort = $sha
  tsUtc = (Get-Date).ToUniversalTime().ToString("o")
  workflow = [ordered]@{
    runId = $env:GITHUB_RUN_ID
    runNumber = $env:GITHUB_RUN_NUMBER
    actor = $env:GITHUB_ACTOR
    ref = $env:GITHUB_REF
    refName = $env:GITHUB_REF_NAME
    refType = $env:GITHUB_REF_TYPE
    repository = $env:GITHUB_REPOSITORY
    serverUrl = $env:GITHUB_SERVER_URL
  }
}

Write-File (Join-Path $tmp "META.json") ($meta | ConvertTo-Json -Depth 10)

# SHA256SUMS
$sumLines = @()
Get-ChildItem -Path $tmp -File -Recurse | ForEach-Object {
  $rel = $_.FullName.Substring($tmp.Length).TrimStart('\','/')
  $sumLines += "$(Sha256 $_.FullName)  $rel"
}
Write-File (Join-Path $tmp "SHA256SUMS") (($sumLines | Sort-Object) -join "`n")

$manifest = @()
$manifest += "# Evidence pack"
$manifest += ""
$manifest += "- domain: $Domain"
$manifest += "- sketch: $Sketch"
$manifest += "- ref: $ref"
$manifest += "- sha: $shaFull"
$manifest += "- tsUtc: $($meta.tsUtc)"
$manifest += ""
$manifest += "Files:"
Get-ChildItem -Path $tmp -File -Recurse | ForEach-Object {
  $rel = $_.FullName.Substring($tmp.Length).TrimStart('\','/')
  $manifest += "- $rel"
}
Write-File (Join-Path $tmp "MANIFEST.md") ($manifest -join "`n")

# 4) Final zip
$outZip = Join-Path $OutDir "$base.zip"
if (Test-Path $outZip) { Remove-Item -Force $outZip }
Write-Host "Create evidence zip: $outZip"
Compress-Archive -Path (Join-Path $tmp "*") -DestinationPath $outZip -Force

Write-Host "OK: $outZip"
Write-Host "SR_EVIDENCE_ZIP=$outZip"

# For GitHub Actions step outputs
if ($env:GITHUB_OUTPUT) {
  "evidence_zip=$outZip" | Out-File -FilePath $env:GITHUB_OUTPUT -Append -Encoding utf8
}
