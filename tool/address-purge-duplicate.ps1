param(
    [Parameter(Mandatory = $true)][string]$RepoRoot,
    [switch]$Apply
)

$RepoRoot = (Resolve-Path $RepoRoot).Path
$patternList = @('*.bak', '* (1).*')

$targetList = New-Object System.Collections.Generic.List[string]
foreach ($pattern in $patternList)
{
    Get-ChildItem -Path $RepoRoot -Recurse -File -Filter $pattern -ErrorAction SilentlyContinue | ForEach-Object {
        $targetList.Add($_.FullName)
    }
}

$targetList = $targetList | Sort-Object -Unique

if ($targetList.Count -eq 0)
{
    Write-Host 'No duplicate candidates found.'
    exit 0
}

Write-Host ('Found {0} duplicate candidates.' -f $targetList.Count)
foreach ($p in $targetList)
{
    if (-not$Apply)
    {
        Write-Host ('[DRY] Remove {0}' -f $p)
        continue
    }
    Write-Host ('Remove {0}' -f $p)
    Remove-Item -LiteralPath $p -Force
}

if (-not$Apply)
{
    Write-Host 'Dry-run complete. Re-run with -Apply to delete.'
}
