param(
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

function Remove-PathSafe([string]$Path, [switch]$Apply)
{
    if (Test-Path $Path)
    {
        if ($Apply)
        {
            Remove-Item -Recurse -Force $Path
            Write-Host "Deleted: $Path"
        }
        else
        {
            Write-Host "[dry-run] Would delete: $Path"
        }
    }
}

function Copy-Overlay([string]$OverlayRoot, [switch]$Apply)
{
    $items = Get-ChildItem -Recurse -File $OverlayRoot
    foreach ($item in $items)
    {
        $rel = $item.FullName.Substring($OverlayRoot.Length).TrimStart('\\', '/')
        $dst = Join-Path (Get-Location) $rel
        $dstDir = Split-Path -Parent $dst
        if (!(Test-Path $dstDir))
        {
            if ($Apply)
            {
                New-Item -ItemType Directory -Force -Path $dstDir | Out-Null
            }
            else
            {
                Write-Host "[dry-run] Would create dir: $dstDir"
            }
        }
        if ($Apply)
        {
            Copy-Item -Force $item.FullName $dst
            Write-Host "Copied: $rel"
        }
        else
        {
            Write-Host "[dry-run] Would copy: $rel"
        }
    }
}

$root = Split-Path -Parent $PSScriptRoot
$overlay = Join-Path $root 'overlay'

Remove-PathSafe -Path 'src/Integration/Geocode' -Apply:$Apply
Remove-PathSafe -Path 'tests/Geocode' -Apply:$Apply

Copy-Overlay -OverlayRoot $overlay -Apply:$Apply

if (-not$Apply)
{
    Write-Host 'Dry-run complete. Re-run with -Apply to apply changes.'
}
