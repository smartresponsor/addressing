# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Out = "report/address-report-validate.json",
  [switch]$AllowMissing
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ($null -eq $Path -or $Path.Trim() -eq "") { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Read-Json([string]$Path) {
  $raw = Get-Content -LiteralPath $Path -Raw -Encoding UTF8
  return ($raw | ConvertFrom-Json -ErrorAction Stop)
}

function Get-JsonType($obj) {
  if ($null -eq $obj) { return "null" }
  if ($obj -is [System.Array]) { return "array" }
  if ($obj -is [hashtable]) { return "object" }
  if ($obj -is [pscustomobject]) { return "object" }
  return "scalar"
}

$items = @(
  [ordered]@{ name="canon-scan"; path="report/address-canon-scan.json" },
  [ordered]@{ name="health-sample"; path="report/address-health-sample.json" },
  [ordered]@{ name="doctor"; path="report/address-doctor.json" }
)

Ensure-Dir (Split-Path -Parent $Out)

$allOk = $true
$checked = @()

foreach ($it in $items) {
  $p = $it.path
  $row = [ordered]@{
    name = $it.name
    path = $p
    ok = $true
    issue = @()
    expected = "object"
    actual = "unknown"
  }

  if (-not (Test-Path $p)) {
    $row.actual = "missing"
    if (-not $AllowMissing) {
      $row.ok = $false
      $row.issue += "missing report: $p"
      $allOk = $false
    }
    $checked += $row
    continue
  }

  try {
    $j = Read-Json $p
    $row.actual = Get-JsonType $j
    if ($row.actual -ne "object") {
      $row.ok = $false
      $row.issue += "$($it.name) type mismatch: expected=object actual=$($row.actual)"
      $allOk = $false
    }
  } catch {
    $row.ok = $false
    $row.actual = "invalid"
    $row.issue += "$($it.name) json parse failed"
    $allOk = $false
  }

  $checked += $row
}

$outObj = [ordered]@{
  component = "address"
  generatedAt = (Get-Date).ToUniversalTime().ToString("o")
  ok = $allOk
  item = $checked
  note = @(
    "This validator is intentionally lightweight and permissive.",
    "It checks JSON parseability and minimal required fields only."
  )
}

($outObj | ConvertTo-Json -Depth 20) | Set-Content -LiteralPath $Out -Encoding UTF8

if ($allOk) {
  Write-Host "OK: $Out"
  exit 0
}

Write-Host "WARN: report validate has failures. See $Out"
exit 2
