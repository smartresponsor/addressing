# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Url,

    [Parameter(Mandatory = $true)]
    [ValidateSet("scan","health","doctor","validate","plan","codex","pr")]
    [string]$Task,

    [string]$Ref = "master",
    [string]$Kind = "fix",
    [string]$Message = "agent update",
    [string]$Note = "",

    [ValidatePattern("^K\d+$")]
    [string]$Kid = "K1",

    [string]$Nonce = ([guid]::NewGuid().ToString())
)

$ErrorActionPreference = "Stop"

if ($PSBoundParameters.ContainsKey("Debug")) {
    $DebugPreference = "Continue"
} else {
    $DebugPreference = "SilentlyContinue"
}

function ConvertTo-HexStringLower {
    param([byte[]]$Bytes)
    ($Bytes | ForEach-Object { $_.ToString("x2") }) -join ""
}

function Get-Sha256Hex {
    param([string]$Value)
    $sha = [System.Security.Cryptography.SHA256]::Create()
    try {
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($Value)
        ConvertTo-HexStringLower ($sha.ComputeHash($bytes))
    } finally {
        $sha.Dispose()
    }
}

function Get-HmacSha256Hex {
    param(
        [string]$Secret,
        [string]$Value
    )
    $hmac = [System.Security.Cryptography.HMACSHA256]::new(
        [System.Text.Encoding]::UTF8.GetBytes($Secret)
    )
    try {
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($Value)
        ConvertTo-HexStringLower ($hmac.ComputeHash($bytes))
    } finally {
        $hmac.Dispose()
    }
}

function Normalize-Secret {
    param([string]$S)
    if ($null -eq $S) { return "" }
    $t = $S.Trim()

    if (($t.StartsWith('"') -and $t.EndsWith('"')) -or ($t.StartsWith("'") -and $t.EndsWith("'"))) {
        if ($t.Length -ge 2) {
            $t = $t.Substring(1, $t.Length - 2)
        }
    }

    return $t.Trim()
}

$kidUp = $Kid.ToUpperInvariant()
$envName = "SR_TRIGGER_SECRET_$kidUp"
$secretRaw = (Get-Item "Env:$envName" -ErrorAction SilentlyContinue).Value
$secret = Normalize-Secret $secretRaw

if (-not $secret) {
    throw "Missing env var $envName"
}

$timestamp = [int][DateTimeOffset]::UtcNow.ToUnixTimeSeconds()

$bodyObj = [ordered]@{
    task   = $Task
    ref    = $Ref
    inputs = [ordered]@{
        kind    = $Kind
        message = $Message
        note    = $Note
    }
}

$body = ($bodyObj | ConvertTo-Json -Depth 10 -Compress)
$body = $body -replace "^\uFEFF", ""
$body = $body -replace "\r?\n", ""

$bodyHash = Get-Sha256Hex $body
$signed = "$timestamp.$bodyHash"
$signature = Get-HmacSha256Hex $secret $signed

$headers = @{
    "X-SR-Timestamp" = "$timestamp"
    "X-SR-Kid"       = $kidUp
    "X-SR-Signature" = $signature
    "X-SR-Nonce"     = $Nonce
}

Write-Host "POST $Url task=$Task kid=$kidUp"

Write-Debug "secretSha256=$(Get-Sha256Hex $secret)"
Write-Debug "timestamp=$timestamp"
Write-Debug "body=$body"
Write-Debug "bodySha256=$bodyHash"
Write-Debug "signed=$signed"
Write-Debug "signature=$signature"
Write-Debug "nonce=$Nonce"

Invoke-RestMethod -Method Post -Uri $Url -Headers $headers -ContentType "application/json" -Body $body
