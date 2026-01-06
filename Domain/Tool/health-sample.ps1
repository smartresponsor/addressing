# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Repo = ".",
    [string]$Out = "report/address-health-sample.json"
)

$ErrorActionPreference = "Stop"

function Ensure-Dir([string]$Path)
{
    if ($null -eq $Path -or $Path.Trim() -eq "")
    {
        return
    }
    if (-not(Test-Path $Path))
    {
        New-Item -ItemType Directory -Force -Path $Path | Out-Null
    }
}

$repoPath = Resolve-Path $Repo
Ensure-Dir (Split-Path -Parent $Out)

$canonScript = Join-Path $PSScriptRoot "canon-check.ps1"

$canonOk = $false
$canonExit = $null

try
{
    $p = Start-Process -FilePath "powershell" -ArgumentList @(
    "-NoProfile", "-ExecutionPolicy", "Bypass",
    "-File", $canonScript,
    "-Repo", $repoPath.Path
    ) -PassThru -Wait -NoNewWindow
    $canonExit = $p.ExitCode
    $canonOk = ($canonExit -eq 0)
}
catch
{
    $canonOk = $false
    $canonExit = 2
}

$now = (Get-Date).ToString("o")
$sample = [ordered]@{
    component = "address"
    generatedAt = $now
    checks = @(
    [ordered]@{
        name = "canon-check"
        ok = $canonOk
        exitCode = $canonExit
        note = "Use Domain/Tool/canon-check.ps1 for details."
    }
    )
    note = @(
    "This is a sample health payload produced by repo-local plugin.",
    "Central Health component may collect and aggregate this output."
    )
}

$json = ($sample | ConvertTo-Json -Depth 8)
Set-Content -Path $Out -Value $json -Encoding UTF8

Write-Host "OK: $Out"

if (-not$canonOk)
{
    Write-Host "WARN: canon-check failed (exit=$canonExit)."
    if ($env:SR_CANON_REQUIRED -ne "1")
    {
        $global:LASTEXITCODE = 0
        $canonExit = 0
        $canonOk = $false
    }
    else
    {
        throw "canon-check failed (exit=$canonExit) and SR_CANON_REQUIRED=1"
    }
}
exit 0

