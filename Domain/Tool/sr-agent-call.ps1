# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

    [CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$Url,
    [Parameter(Mandatory = $true)]
    [ValidateSet("scan", "health", "doctor", "validate", "plan", "codex", "pr")]
    [string]$Task,
    [string]$Ref = "master",
    [string]$Kind = "fix",
    [string]$Message = "agent update",
    [string]$Note = "",
    [ValidatePattern("^K\d+$")]
    [string]$Kid = "K1"
)

$ErrorActionPreference = "Stop"

function To-Hex([byte[]]$Bytes)
{
    ($Bytes | ForEach-Object { $_.ToString("x2") }) -join ""
}

function Hmac-Sha256-Hex([string]$Secret, [string]$Data)
{
    $h = [System.Security.Cryptography.HMACSHA256]::new(
            [System.Text.Encoding]::UTF8.GetBytes($Secret)
    )
    try
    {
        return To-Hex ($h.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($Data)))
    }
    finally
    {
        $h.Dispose()
    }
}

$kidUp = $Kid.ToUpper()
$envName = "SR_TRIGGER_SECRET_$kidUp"
$secret = (Get-Item "Env:$envName" -ErrorAction SilentlyContinue).Value
if (-not$secret)
{
    throw "Missing env var $envName"
}

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

function Canonical-Json($obj) {
    if ($obj -is [hashtable]) {
        $ordered = [ordered]@{}
        foreach ($k in ($obj.Keys | Sort-Object)) {
            $ordered[$k] = Canonical-Json $obj[$k]
        }
        return $ordered
    }
    if ($obj -is [array]) {
        return @($obj | ForEach-Object { Canonical-Json $_ })
    }
    return $obj
}

$canonical = Canonical-Json $bodyObj
$body = ($canonical | ConvertTo-Json -Depth 6 -Compress)

$sig = Hmac-Sha256-Hex $secret "$ts.$body"

$headers = @{
    "Content-Type" = "application/json"
    "X-SR-Timestamp" = "$ts"
    "X-SR-Signature" = $sig
    "X-SR-Kid" = $kidUp
}

Write-Host "POST $Url task=$Task kid=$kidUp"

$response = Invoke-RestMethod `
  -Method Post `
  -Uri $Url `
  -Headers $headers `
  -Body $body

$response | ConvertTo-Json -Depth 6
