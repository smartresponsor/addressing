param(
    [string]$Repo = "D:\PhpstormProjects\www\Addressing"
)

$RemovePaths = @(
    "tools\addressing-w02",
    "tools\addressing-w03",
    "tools\addressing-w03b",
    "tools\addressing-w04",
    "tools\addressing-w05",
    ("tools\smoke\" + "category" + "-runtime-smoke.php"),
    ("tools\smoke\" + "category" + "-fixture-sanity.php"),
    ("tools\smoke\" + "category" + "-container-boot-smoke.php"),
    ("tools\smoke\" + "category" + "-fixture-load-smoke.php"),
    ("tools\smoke\" + "category" + "-doctrine-mapping-smoke.php"),
    ("tools\smoke\" + "category" + "-graphql-smoke.php"),
    ("src\Service\Application\" + "Address" + "Service.php"),
    ("src\Service\Http\Address\" + "Address" + "Http" + "Service.php"),
    ("tests\Unit\" + "Address" + "Service" + "UnitTest.php"),
    ("tests\Functional\" + "Address" + "Http" + "Service" + "FunctionalTest.php"),
    ("src\RepositoryInterface\Persistence\" + "Address" + "Repository" + "Interface.php")
)

foreach ($Path in $RemovePaths) {
    $Full = Join-Path $Repo $Path
    if (Test-Path $Full) {
        Remove-Item $Full -Recurse -Force
        Write-Host "Removed: $Path" -ForegroundColor Yellow
    }
}

Get-ChildItem $Repo -Directory -Recurse |
    Sort-Object FullName -Descending |
    Where-Object { @(Get-ChildItem $_.FullName -Force -ErrorAction SilentlyContinue).Count -eq 0 } |
    ForEach-Object {
        Remove-Item $_.FullName -Force
        Write-Host "Removed empty dir: $($_.FullName.Replace($Repo + '\', ''))" -ForegroundColor DarkYellow
    }

Write-Host "W06 RC gate cleanup applied." -ForegroundColor Green
