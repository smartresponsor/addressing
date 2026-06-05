$Repo = "D:\PhpstormProjects\www\Addressing"

$RemovePaths = @(
    "src\Repository\Persistence\DoctrineAddressRepository.php",
    "tests\Integration"
)

foreach ($Path in $RemovePaths) {
    $Full = Join-Path $Repo $Path
    if (Test-Path $Full) {
        Remove-Item $Full -Recurse -Force
        Write-Host "Removed: $Path" -ForegroundColor Yellow
    } else {
        Write-Host "Already absent: $Path" -ForegroundColor DarkGray
    }
}

Get-ChildItem $Repo -Directory -Recurse |
    Sort-Object FullName -Descending |
    Where-Object {
        $_.FullName -notmatch "\\vendor\\" -and
        $_.FullName -notmatch "\\var\\cache\\" -and
        @(Get-ChildItem $_.FullName -Force -ErrorAction SilentlyContinue).Count -eq 0
    } |
    ForEach-Object {
        Remove-Item $_.FullName -Force
        Write-Host "Removed empty dir: $($_.FullName.Replace($Repo + '\', ''))" -ForegroundColor DarkYellow
    }

Write-Host ""
Write-Host "W07 stale-file delete cleanup done." -ForegroundColor Green
