param(
  [string]$Root = (Resolve-Path ".").Path
)

$ErrorActionPreference = "Stop"
$ok = $true

# Check composer.json exists and has App\ autoload
$composer = Join-Path $Root "composer.json"
if (-not (Test-Path $composer)) {
  Write-Error "composer.json not found"
  exit 1
}
$composerJson = Get-Content $composer -Raw | ConvertFrom-Json
if (-not $composerJson.autoload.'psr-4'.'App\') {
  Write-Error "composer.json autoload missing App\ => src/"
  $ok = $false
}

# Scan PHP files for strict_types
$phpFiles = Get-ChildItem -Path (Join-Path $Root "src") -Recurse -Filter *.php -ErrorAction SilentlyContinue
$missingStrict = @()
foreach ($f in $phpFiles) {
  $content = Get-Content $f.FullName -Raw
  if ($content -notmatch 'declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;') {
    $missingStrict += $f.FullName
  }
}

if ($missingStrict.Count -gt 0) {
  Write-Host "Files missing strict_types:"
  $missingStrict | ForEach-Object { Write-Host " - $_" }
  $ok = $false
}

if ($ok) { Write-Host "SMOKE OK"; exit 0 } else { Write-Host "SMOKE FAIL"; exit 2 }
