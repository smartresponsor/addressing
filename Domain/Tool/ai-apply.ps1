# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
#
# Safe patch applier (opt-in).
# - Does not run by default.
# - Requires SR_ALLOW_APPLY=1.
# - Applies a unified diff patch using git apply.

param(
    [Parameter(Mandatory = $true)]
    [string]$Patch,
    [string]$Repo = ".",
    [string]$TestCmd = "",
    [switch]$NoCanonCheck
)

$ErrorActionPreference = "Stop"

function Resolve-CommandLine([string]$CommandLine)
{
    $tokens = $null
    $errors = $null

    $ast = [System.Management.Automation.Language.Parser]::ParseInput(
            $CommandLine,
            [ref]$tokens,
            [ref]$errors
    )

    if ($errors -and $errors.Count -gt 0)
    {
        $details = ($errors | ForEach-Object { $_.Message }) -join "; "
        throw "Failed to parse command line: $details"
    }

    $commandAst = $ast.EndBlock.Statements |
            Where-Object { $_ -is [System.Management.Automation.Language.CommandAst] } |
            Select-Object -First 1

    if (-not$commandAst)
    {
        throw "No command found in: $CommandLine"
    }

    $elements = $commandAst.CommandElements

    $commandElement = $elements[0]
    if ($commandElement -is [System.Management.Automation.Language.StringConstantExpressionAst])
    {
        $filePath = $commandElement.Value
    }
    else
    {
        $filePath = $commandElement.Extent.Text
    }

    $args = @()
    for ($i = 1; $i -lt $elements.Count; $i++) {
        $element = $elements[$i]
        if ($element -is [System.Management.Automation.Language.StringConstantExpressionAst])
        {
            $args += $element.Value
        }
        else
        {
            $args += $element.Extent.Text
        }
    }

    return @{
        FilePath = $filePath
        Arguments = $args
    }
}

function Format-CommandForLog(
        [string]$FilePath,
        [string[]]$Arguments
)
{
    $redactFlags = @(
    "--password", "--token", "--secret",
    "--apikey", "--api-key", "--key", "--pwd"
    )

    $redactPatterns = @(
    "(?i)password=",
    "(?i)token=",
    "(?i)secret=",
    "(?i)api[-]?key=",
    "(?i)key=",
    "(?i)pwd="
    )

    $sanitized = @()
    $skipNext = $false

    foreach ($arg in $Arguments)
    {

        if ($skipNext)
        {
            $sanitized += "<redacted>"
            $skipNext = $false
            continue
        }

        if ($redactFlags -contains $arg)
        {
            $sanitized += $arg
            $skipNext = $true
            continue
        }

        $redacted = $false
        foreach ($pattern in $redactPatterns)
        {
            if ($arg -match $pattern)
            {
                $sanitized += ($arg -replace "(=).*", "=<redacted>")
                $redacted = $true
                break
            }
        }

        if (-not$redacted)
        {
            $sanitized += $arg
        }
    }

    return (@($FilePath) + $sanitized) -join " "
}

function Invoke-CommandLine([string]$CommandLine)
{
    $parsed = Resolve-CommandLine $CommandLine
    $commandForLog = Format-CommandForLog `
    $parsed.FilePath
    $parsed.Arguments

    Write-Host "Running: $commandForLog"

    $process = Start-Process `
    -FilePath $parsed.FilePath `
    -ArgumentList $parsed.Arguments `
    -NoNewWindow `
    -Wait `
    -PassThru

    if ($process.ExitCode -ne 0)
    {
        throw "Command failed: exit=$( $process.ExitCode )"
    }
}

if ($env:SR_ALLOW_APPLY -ne "1")
{
    throw "Apply is disabled. Set SR_ALLOW_APPLY=1 to proceed."
}

if (-not(Test-Path $Patch))
{
    throw "Patch file not found: $Patch"
}

$git = Get-Command git -ErrorAction SilentlyContinue
if (-not$git)
{
    throw "git not found in PATH"
}

Push-Location $Repo
try
{

    $status = & $git.Source status --porcelain
    if ($status -and $status.Trim().Length -gt 0)
    {
        throw "Working tree is not clean. Commit or stash changes before apply."
    }

    Write-Host "Applying patch: $Patch"
    & $git.Source apply --whitespace = nowarn --verbose $Patch

    if (-not$NoCanonCheck)
    {
        $canonCheck = Join-Path $PWD "Domain/Tool/canon-check.ps1"
        if (Test-Path $canonCheck)
        {
            Write-Host "Running canon check..."
            & $canonCheck
        }
        else
        {
            Write-Host "Canon check script not found (skipping)"
        }
    }

    if ($TestCmd -and $TestCmd.Trim().Length -gt 0)
    {
        Invoke-CommandLine $TestCmd
    }

    Write-Host "OK: patch applied"

}
finally
{
    Pop-Location
}
