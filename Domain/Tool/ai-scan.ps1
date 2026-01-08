# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Domain = "",
  [string]$Out = ""
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ([string]::IsNullOrWhiteSpace($Path)) { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

function Get-FullPath([string]$Path) {
  return [System.IO.Path]::GetFullPath($Path)
}

function Parse-CommandLine([string]$CommandLine) {
  $tokens = $null
  $errors = $null
  $ast = [System.Management.Automation.Language.Parser]::ParseInput($CommandLine, [ref]$tokens, [ref]$errors)
  if ($errors -and $errors.Count -gt 0) {
    $details = ($errors | ForEach-Object { $_.Message }) -join "; "
    throw "Failed to parse command line: $details"
  }

  $commandAst = $ast.EndBlock.Statements |
    Where-Object { $_ -is [System.Management.Automation.Language.CommandAst] } |
    Select-Object -First 1
  if (-not $commandAst) { throw "No command found in: $CommandLine" }

  $elements = $commandAst.CommandElements
  $commandElement = $elements[0]
  if ($commandElement -is [System.Management.Automation.Language.StringConstantExpressionAst]) {
    $filePath = $commandElement.Value
  } else {
    $filePath = $commandElement.Extent.Text
  }
  $args = @()
  for ($i = 1; $i -lt $elements.Count; $i++) {
    $element = $elements[$i]
    if ($element -is [System.Management.Automation.Language.StringConstantExpressionAst]) {
      $args += $element.Value
    } else {
      $args += $element.Extent.Text
    }
  }

  return @{
    FilePath = $filePath
    Arguments = $args
  }
}

function Format-CommandForLog([string]$FilePath, [string[]]$Arguments) {
  $redactFlags = @("--password", "--token", "--secret", "--apikey", "--api-key", "--key", "--pwd")
  $redactPatterns = @("(?i)password=", "(?i)token=", "(?i)secret=", "(?i)api[-]?key=", "(?i)key=", "(?i)pwd=")
  $sanitized = @()
  $skipNext = $false

  foreach ($arg in $Arguments) {
    if ($skipNext) {
      $sanitized += "<redacted>"
      $skipNext = $false
      continue
    }

    if ($redactFlags -contains $arg) {
      $sanitized += $arg
      $skipNext = $true
      continue
    }

    $redacted = $false
    foreach ($pattern in $redactPatterns) {
      if ($arg -match $pattern) {
        $sanitized += ($arg -replace "(=).*", "=<redacted>")
        $redacted = $true
        break
      }
    }
    if (-not $redacted) { $sanitized += $arg }
  }

  return (@($FilePath) + $sanitized) -join " "
}

function Invoke-CommandLine([string]$CommandLine) {
  $parsed = Parse-CommandLine $CommandLine
  $commandForLog = Format-CommandForLog $parsed.FilePath $parsed.Arguments
  Write-Host "Running: $commandForLog"

  $process = Start-Process -FilePath $parsed.FilePath -ArgumentList $parsed.Arguments -NoNewWindow -Wait -PassThru
  if ($process.ExitCode -ne 0) {
    throw "Command failed: exit=$($process.ExitCode)"
  }
}

function Resolve-Domain([string]$d) {
  if (-not [string]::IsNullOrWhiteSpace($d)) { return $d }
  if ($env:SR_DOMAIN -and -not [string]::IsNullOrWhiteSpace($env:SR_DOMAIN)) { return $env:SR_DOMAIN.Trim() }
  return "component"
}

function Find-CanonBin {
  $candidates = @(
    "vendor/bin/sr-canon",
    "vendor/bin/smartresponsor-canon",
    "vendor/bin/canon"
  )
  foreach ($c in $candidates) {
    if (Test-Path $c) { return $c }
  }
  return $null
}

$Domain = Resolve-Domain $Domain

if ([string]::IsNullOrWhiteSpace($Out)) {
  $Out = ("report/{0}-canon-scan.json" -f $Domain)
}

Ensure-Dir (Split-Path -Parent $Out)

$bin = Find-CanonBin
if (-not $bin) {
  if ($env:SR_CANON_SCAN_CMD -and $env:SR_CANON_SCAN_CMD.Trim() -ne "") {
    $cmd = $env:SR_CANON_SCAN_CMD.Replace("{out}", $Out)
    Write-Host ("Output: {0}" -f (Get-FullPath $Out))
    Invoke-CommandLine $cmd
    if (-not (Test-Path $Out)) {
      throw "Canon scan finished but output not found: $Out"
    }
    Write-Host "OK: $Out"
    exit 0
  }

  $required = ($env:SR_CANON_REQUIRED -eq "1")
  if ($required) {
    throw "Canon CLI not found. Install Canon or set SR_CANON_SCAN_CMD (use {out})."
  }

  Write-Host "WARN: Canon CLI not found. Writing skipped scan report."

  $skipped = [ordered]@{
    ok = $true
    status = "skipped"
    reason = "canon_cli_missing"
    hint = "Set SR_CANON_SCAN_CMD (use {out}) or add Canon as dev dependency."
    tsUtc = (Get-Date).ToUniversalTime().ToString("o")
  }

  ($skipped | ConvertTo-Json -Depth 10) | Set-Content -Path $Out -Encoding UTF8
  Write-Host "OK: $Out"
  exit 0
}

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) { throw "php not found in PATH" }

Write-Host ("Output: {0}" -f (Get-FullPath $Out))
Write-Host "Running: php $bin scan --format json --out $Out"
& $php.Source $bin scan --format json --out $Out
if ($LASTEXITCODE -ne 0) {
  throw "Canon scan failed: exit=$LASTEXITCODE"
}

if (-not (Test-Path $Out)) { throw "Output not found: $Out" }
Write-Host "OK: $Out"
exit 0
