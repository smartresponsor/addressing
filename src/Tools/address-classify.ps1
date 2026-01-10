param(
    [Parameter(Mandatory = $true)][string]$RepoRoot,
    [string]$OutCsv = 'address-split-candidate.csv'
)

$RepoRoot = (Resolve-Path $RepoRoot).Path
$OutPath = if ( [System.IO.Path]::IsPathRooted($OutCsv))
{
    $OutCsv
}
else
{
    Join-Path $RepoRoot $OutCsv
}

function ClassifyPath([string]$rel)
{
    $p = $rel.Replace('\', '/').ToLowerInvariant()
    if ( $p.StartsWith('sql/'))
    {
        return 'AddressData'
    }
    if ( $p.StartsWith('src/entity/'))
    {
        return 'AddressData'
    }
    if ( $p.StartsWith('src/repository/'))
    {
        return 'AddressData'
    }
    if ( $p.StartsWith('src/service/address/'))
    {
        return 'AddressData'
    }
    if ( $p.Contains('/utility/addressengine/'))
    {
        return 'AddressEngine'
    }
    if ( $p.Contains('/service/parse/'))
    {
        return 'AddressEngine'
    }
    if ( $p.Contains('/service/normalize/'))
    {
        return 'AddressEngine'
    }
    if ( $p.Contains('/locator'))
    {
        return 'AddressLocator'
    }
    if ( $p.Contains('/integration/'))
    {
        return 'AddressLocator'
    }
    return 'Unknown'
}

$rows = New-Object System.Collections.Generic.List[object]
Get-ChildItem -Path $RepoRoot -Recurse -File -Include *.php, *.yaml, *.yml, *.sql, *.md -ErrorAction SilentlyContinue | ForEach-Object {
    $rel = $_.FullName.Substring($RepoRoot.Length).TrimStart('\', '/')
    $c = ClassifyPath $rel
    $rows.Add([PSCustomObject]@{ path = $rel; candidate = $c })
}

$rows | Export-Csv -Path $OutPath -NoTypeInformation -Encoding UTF8
Write-Host ('Wrote {0} rows to {1}' -f $rows.Count, $OutPath)
