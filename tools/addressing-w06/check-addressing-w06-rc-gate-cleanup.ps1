param(
    [string]$Repo = "D:\PhpstormProjects\www\Addressing"
)

$Failed = @()

$MustBeAbsent = @(
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
    ("tools\smoke\" + "category" + "-graphql-smoke.php")
)

foreach ($Path in $MustBeAbsent) {
    if (Test-Path (Join-Path $Repo $Path)) {
        $Failed += "Unexpected legacy path remains: $Path"
    }
}

$MustExist = @(
    "tools\smoke\address-runtime-smoke.php",
    "tools\smoke\address-fixture-sanity.php",
    "tools\smoke\address-container-boot-smoke.php",
    "tools\smoke\address-fixture-load-smoke.php",
    "tools\smoke\address-doctrine-mapping-smoke.php",
    "tools\smoke\address-graphql-smoke.php"
)

foreach ($Path in $MustExist) {
    if (!(Test-Path (Join-Path $Repo $Path))) {
        $Failed += "Missing canonical path: $Path"
    }
}

$LiveFiles = Get-ChildItem $Repo -Recurse -File -Include *.php,*.yaml,*.json,*.ps1 |
    Where-Object {
        $_.FullName -notmatch "\\vendor\\" -and
        $_.FullName -notmatch "\\docs\\" -and
        $_.FullName -notmatch "\\report\\"
    }

$LegacyTokens = @(
    "Address" + "Service",
    "Address" + "Http" + "Service",
    "Address" + "Repository" + "Interface",
    ("category" + "-")
)

foreach ($Token in $LegacyTokens) {
    $Hits = $LiveFiles | Select-String -Pattern $Token -SimpleMatch -ErrorAction SilentlyContinue
    if ($Hits) {
        $Failed += "Unexpected live token remains: $Token"
        $Hits | Select-Object -First 20 | ForEach-Object { Write-Host $_ -ForegroundColor Red }
    }
}

if ($Failed.Count -gt 0) {
    $Failed | ForEach-Object { Write-Host $_ -ForegroundColor Red }
    throw "W06 RC gate cleanup check failed."
}

Write-Host "W06 RC gate cleanup check passed." -ForegroundColor Green
