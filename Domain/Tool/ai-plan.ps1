# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [string]$Scan = "report/address-canon-scan.json",
  [string]$Out = "report/address-ai-plan.md",
  [string]$Raw = "report/address-ai-plan.raw.json"
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path) {
  if ($null -eq $Path -or $Path.Trim() -eq "") { return }
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Force -Path $Path | Out-Null }
}

if (-not (Test-Path $Scan)) {
  throw "Scan file not found: $Scan. Run Domain/Tool/ai-scan.ps1 first."
}

if (-not $env:OPENAI_API_KEY -or $env:OPENAI_API_KEY.Trim() -eq "") {
  throw "OPENAI_API_KEY is required."
}

$model = $env:SR_MODEL
if (-not $model -or $model.Trim() -eq "") { $model = "gpt-5-mini" }

$effort = $env:SR_REASONING_EFFORT
if (-not $effort -or $effort.Trim() -eq "") { $effort = "medium" }

$promptPath = Join-Path (Split-Path -Parent $MyInvocation.MyCommand.Path) "..\Ai\prompt\address-plan.md"
$promptPath = Resolve-Path $promptPath

$instructions = Get-Content -Path $promptPath -Raw -Encoding UTF8
$scanJson = Get-Content -Path $Scan -Raw -Encoding UTF8

Ensure-Dir (Split-Path -Parent $Out)
Ensure-Dir (Split-Path -Parent $Raw)

$body = @{
  model = $model
  instructions = $instructions
  input = "Canon scan JSON:`n$scanJson"
  max_output_tokens = 7000
  store = $false
  reasoning = @{ effort = $effort }
  text = @{ format = @{ type = "text" } }
} | ConvertTo-Json -Depth 12

$headers = @{
  "Authorization" = "Bearer $($env:OPENAI_API_KEY)"
  "Content-Type"  = "application/json"
}

$resp = Invoke-RestMethod -Method Post -Uri "https://api.openai.com/v1/responses" -Headers $headers -Body $body

$resp | ConvertTo-Json -Depth 20 | Set-Content -Path $Raw -Encoding UTF8

$outText = ""
foreach ($o in $resp.output) {
  if ($o.type -ne "message") { continue }
  foreach ($c in $o.content) {
    if ($c.type -eq "output_text") {
      $outText += $c.text + "`n"
    }
  }
}

if (-not $outText -or $outText.Trim() -eq "") {
  throw "Empty output. See raw response: $Raw"
}

$outText.TrimEnd() | Set-Content -Path $Out -Encoding UTF8
Write-Host "OK: $Out"
