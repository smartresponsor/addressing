# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Domain = "",
    [string]$Repo = ".",
    [switch]$Strict
)

$ErrorActionPreference = "Stop"

function Read-OkFlag([string]$Path)
{
    if (-not(Test-Path $Path))
    {
        return $true
    }
    try
    {
        $j = Get-Content $Path -Raw -ErrorAction Stop | ConvertFrom-Json -ErrorAction Stop
        if ($null -ne $j.ok)
        {
            return [bool]$j.ok
        }
    }
    catch
    {
        return $false
    }
    return $true
}

function Resolve-Domain([string]$d)
{
    if (-not [string]::IsNullOrWhiteSpace($d))
    {
        return $d
    }
    if ($env:SR_DOMAIN -and -not [string]::IsNullOrWhiteSpace($env:SR_DOMAIN))
    {
        return $env:SR_DOMAIN.Trim()
    }
    return "component"
}

$Domain = Resolve-Domain $Domain

$repoPath = Resolve-Path -Path $Repo -ErrorAction Stop
Set-Location $repoPath

$run = Join-Path $repoPath "Domain/Tool/run.ps1"
if (-not(Test-Path $run))
{
    throw "Missing Domain/Tool/run.ps1"
}

$strictMode = $Strict.IsPresent -or ($env:SR_GATE_STRICT -eq "1")

$fail = $false

# doctor
& pwsh -NoProfile -File $run doctor -Domain $Domain -Repo $repoPath
$doctorExit = $LASTEXITCODE
$doctorReport = Join-Path $repoPath ("report/{0}-doctor.json" -f $Domain)
$doctorOk = Read-OkFlag $doctorReport
if (-not$doctorOk)
{
    Write-Host ("WARN: doctor report ok=false (exit={0}). See artifact {1}" -f $doctorExit, $doctorReport)
    if ($strictMode)
    {
        $fail = $true
    }
}
if ($doctorExit -ne 0)
{
    Write-Host ("WARN: doctor exit={0} (continuing)." -f $doctorExit)
}
$global:LASTEXITCODE = 0

# scan (optional)
& pwsh -NoProfile -File $run scan -Domain $Domain
$global:LASTEXITCODE = 0

# health (warn-only)
& pwsh -NoProfile -File $run health -Domain $Domain -Repo $repoPath
$global:LASTEXITCODE = 0

# validate
& pwsh -NoProfile -File $run validate -Domain $Domain -Repo $repoPath -AllowMissing
$valExit = $LASTEXITCODE
$valReport = Join-Path $repoPath ("report/{0}-report-validate.json" -f $Domain)
$valOk = Read-OkFlag $valReport
if (-not$valOk)
{
    Write-Host ("WARN: validate report ok=false (exit={0}). See artifact {1}" -f $valExit, $valReport)
    if ($strictMode)
    {
        $fail = $true
    }
}
if ($valExit -ne 0)
{
    Write-Host ("WARN: validate exit={0} (continuing)." -f $valExit)
}
$global:LASTEXITCODE = 0

if ($fail)
{
    Write-Host "ERROR: Gate strict mode enabled and one or more reports have ok=false."
    exit 2
}

Write-Host "OK: Gate soft-pass."
exit 0
