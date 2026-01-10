param(
    [Parameter(Mandatory = $true)]
    [int]$Pr
)

$branch = "pr-$Pr"
$bundle = "addressing-pr-$Pr.bundle"

Write-Host "Fetching PR #$Pr..."
git fetch origin "pull/$Pr/head:$branch"
if ($LASTEXITCODE -ne 0) {
    Write-Error "git fetch failed"
    exit 1
}

Write-Host "Creating bundle $bundle..."
git bundle create $bundle "master..$branch"
if ($LASTEXITCODE -ne 0) {
    Write-Error "git bundle create failed"
    exit 1
}

Write-Host "Bundle created: $bundle"
