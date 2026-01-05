# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Repo = ".",
  [string]$Out = "report/address-report-validate.json",
  [switch]$AllowMissing
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ($null -eq $Path -or $Path.Trim() -eq "") { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Load-Json([string]$Path) {
  $raw = Get-Content -Path $Path -Raw -Encoding UTF8
  return ($raw | ConvertFrom-Json)
}

function Get-TypeName($Value) {
  if ($null -eq $Value) { return "null" }
  if ($Value -is [System.Collections.IDictionary]) { return "object" }
  if ($Value -is [System.Collections.IEnumerable] -and -not ($Value -is [string])) { return "array" }
  if ($Value -is [bool]) { return "boolean" }
  if ($Value -is [int] -or $Value -is [long]) { return "integer" }
  if ($Value -is [double] -or $Value -is [decimal] -or $Value -is [float]) { return "number" }
  if ($Value -is [string]) { return "string" }
  return "unknown"
}

function Test-Type([object]$Value, [string]$Expected) {
  $actual = Get-TypeName $Value
  if ($Expected -eq "number") { return ($actual -eq "number" -or $actual -eq "integer") }
  return ($actual -eq $Expected)
}

function Validate-Shape([object]$Data, [object]$Schema, [string]$Prefix) {
  $issue = @()

  if ($Schema.type -and -not (Test-Type $Data $Schema.type)) {
    $issue += "$Prefix type mismatch: expected=$($Schema.type) actual=$(Get-TypeName $Data)"
    return $issue
  }

  if ($Schema.required) {
    foreach ($r in $Schema.required) {
      $has = $false
      try {
        $v = $Data.$r
        if ($null -ne $v) { $has = $true }
        else {
          # property exists but null
          $has = $true
        }
      } catch { $has = $false }
      if (-not $has) { $issue += "$Prefix missing required: $r" }
    }
  }

  if ($Schema.properties) {
    foreach ($p in $Schema.properties.PSObject.Properties.Name) {
      try {
        $v = $Data.$p
      } catch {
        continue
      }

      if ($null -eq $v) { continue }

      $pSchema = $Schema.properties.$p
      if ($pSchema.type) {
        if (-not (Test-Type $v $pSchema.type)) {
          $issue += "$Prefix.$p type mismatch: expected=$($pSchema.type) actual=$(Get-TypeName $v)"
          continue
        }
      }

      if ($pSchema.type -eq "array" -and $pSchema.items) {
        $idx = 0
        foreach ($it in $v) {
          $issue += Validate-Shape $it $pSchema.items "$Prefix.$p[$idx]"
          $idx++
          if ($idx -ge 200) { break }
        }
      }
    }
  }

  return $issue
}

$repoPath = Resolve-Path $Repo
Ensure-Dir (Split-Path -Parent $Out)

$schemaDir = Join-Path $repoPath.Path "Domain\Ai\schema"
if (-not (Test-Path $schemaDir)) {
  throw "Schema folder not found: Domain\Ai\schema"
}

$map = @(
  @{ name = "canon-scan";  path = "report/address-canon-scan.json";  schema = "address-canon-scan.schema.json" },
  @{ name = "health-sample"; path = "report/address-health-sample.json"; schema = "address-health-sample.schema.json" },
  @{ name = "doctor";     path = "report/address-doctor.json";       schema = "address-doctor.schema.json" }
)

$item = @()
$allOk = $true

foreach ($m in $map) {
  $name = $m.name
  $relPath = $m.path
  $schemaName = $m.schema

  $p = Join-Path $repoPath.Path $relPath
  $s = Join-Path $schemaDir $schemaName

  $oneIssue = @()
  $oneOk = $true

  if (-not (Test-Path $s)) {
    $oneOk = $false
    $oneIssue += "Schema not found: $schemaName"
  }

  if (-not (Test-Path $p)) {
    if ($AllowMissing) {
      $oneOk = $true
      $oneIssue += "Missing file (allowed): $relPath"
    } else {
      $oneOk = $false
      $oneIssue += "Missing file: $relPath"
    }
  } else {
    try {
      $data = Load-Json $p
    } catch {
      $oneOk = $false
      $oneIssue += "JSON parse failed: $relPath"
    }

    if ($oneOk -and (Test-Path $s)) {
      try {
        $schema = Load-Json $s
        $shapeIssue = Validate-Shape $data $schema $name
        if ($shapeIssue.Count -gt 0) {
          $oneOk = $false
          $oneIssue += $shapeIssue
        }
      } catch {
        $oneOk = $false
        $oneIssue += "Schema validate failed: $($_.Exception.Message)"
      }
    }
  }

  if (-not $oneOk) { $allOk = $false }

  $item += [ordered]@{
    name = $name
    path = $relPath
    ok = $oneOk
    issue = $oneIssue
  }
}

$now = (Get-Date).ToString("o")
$outPayload = [ordered]@{
  component = "address"
  generatedAt = $now
  ok = $allOk
  item = $item
  note = @(
    "This validator is intentionally lightweight and permissive.",
    "It checks JSON parseability and minimal required fields only."
  )
}

($outPayload | ConvertTo-Json -Depth 14) | Set-Content -Path $Out -Encoding UTF8
Write-Host "OK: $Out"

if (-not $allOk) { exit 2 }
exit 0
