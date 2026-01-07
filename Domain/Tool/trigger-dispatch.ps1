# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
  [Parameter(Mandatory=$true)][string]$Url,
  [Parameter(Mandatory=$true)][ValidateSet("scan","health","doctor","validate","plan","codex","pr")][string]$Task,
  [string]$Ref = "master",
  [string]$Kind = "fix",
  [string]$Message = "agent update",
  [string]$Note = ""
)

$ErrorActionPreference = "Stop"

function To-Hex([byte[]]$Bytes) { return ($Bytes | ForEach-Object { $_.ToString("x2") }) -join "" }

function Hmac-Sha256-Hex([string]$Secret, [string]$Data) {
  $h = New-Object System.Security.Cryptography.HMACSHA256
  try {
    $h.Key = [System.Text.Encoding]::UTF8.GetBytes($Secret)
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($Data)
    return (To-Hex ($h.ComputeHash($bytes)))
  } finally { $h.Dispose() }
}

$secret = $env:SR_TRIGGER_SECRET
if (-not $secret) { throw "SR_TRIGGER_SECRET env var is required" }

$ts = [int][DateTimeOffset]::UtcNow.ToUnixTimeSeconds()

$bodyObj = @{
  task = $Task
  ref = $Ref
  inputs = @{
    kind = $Kind
    message = $Message
    note = $Note
  }
}
$body = ($bodyObj | ConvertTo-Json -Depth 6 -Compress)

$sig = Hmac-Sha256-Hex $secret "$ts.$body"

$headers = @{
  "X-SR-Timestamp" = "$ts"
  "X-SR-Signature" = $sig
  "Content-Type" = "application/json"
}

Write-Host "POST $Url task=$Task ref=$Ref"
$res = Invoke-RestMethod -Method Post -Uri $Url -Headers $headers -Body $body
$res | ConvertTo-Json -Depth 6
