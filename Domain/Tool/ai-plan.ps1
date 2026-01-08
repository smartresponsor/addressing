# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

param(
    [string]$Domain = "",
    [string]$Scan = "",
    [string]$Out = ""
)

$ErrorActionPreference = "Stop"

. "$PSScriptRoot/lib/common.ps1"

$Domain = Resolve-Domain $Domain

if ( [string]::IsNullOrWhiteSpace($Scan))
{
    $Scan = ("report/{0}-canon-scan.json" -f $Domain)
}

if ( [string]::IsNullOrWhiteSpace($Out))
{
    $Out = ("docs/agent/{0}-ai-plan.md" -f $Domain)
}

Ensure-Dir (Split-Path -Parent $Out)

$scanObj = $null
if (Test-Path $Scan)
{
    try
    {
        $scanObj = Get-Content $Scan -Raw | ConvertFrom-Json
    }
    catch
    {
        $scanObj = $null
    }
}

$lines = @()
$lines += ("# AI plan ({0})" -f $Domain)
$lines += ""
$lines += ("Generated: {0} UTC" -f (Get-Date).ToUniversalTime().ToString("o"))
$lines += ""

if ($scanObj -and $scanObj.status -eq "skipped")
{

    $lines += "Status: skipped"
    $lines += ""
    $lines += "Reason:"

    if ($scanObj.PSObject.Properties.Match("reason").Count -gt 0 -and $scanObj.reason)
    {
        $lines += $scanObj.reason
    }
    else
    {
        $lines += "unknown"
    }

}
elseif (-not$scanObj)
{

    $lines += "Status: no scan data"
    $lines += ""
    $lines += "Action:"
    $lines += "- Run canon scan first"

}
else
{

    $lines += "Status: scan loaded"
    $lines += ""

    if ($scanObj.summary)
    {
        $lines += "Summary:"
        foreach ($prop in $scanObj.summary.PSObject.Properties)
        {
            $lines += ("- {0}: {1}" -f $prop.Name, $prop.Value)
        }
        $lines += ""
    }

    $lines += "Next steps:"
    $lines += "- Review findings"
    $lines += "- Build RWE envelopes"
    $lines += "- Schedule implementation batches"
}

$lines | Set-Content -Path $Out -Encoding UTF8
