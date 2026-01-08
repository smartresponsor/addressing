# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Repo = "."
)

$ErrorActionPreference = "Stop"

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
$bin = Find-CanonBin $repoPath.Path

if (-not $bin) {
  if ($env:SR_CANON_CHECK_CMD -and $env:SR_CANON_CHECK_CMD.Trim() -ne "") {
    $cmd = $env:SR_CANON_CHECK_CMD.Replace("{repo}", $repoPath.Path)
    Invoke-CommandLine $cmd
    exit 0
  }

  throw "Canon CLI not found. Install Canon as dev dependency or set SR_CANON_CHECK_CMD (use {repo})."
}

$php = (Get-Command php -ErrorAction SilentlyContinue)
if (-not $php) { throw "php not found in PATH" }

Push-Location $repoPath.Path
try {
  Write-Host "Running: php $bin check"
  & $php.Source $bin check
  if ($LASTEXITCODE -ne 0) {
    throw "Canon check failed: exit=$LASTEXITCODE"
  }
  exit 0
} finally {
  Pop-Location
}
