# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

# --- Worker template (optional copy) ---
if (Test-Path -LiteralPath $wkTpl) {
  $wkOut = Join-Path $repoRoot "Domain/Ai/agent-trigger/worker"
  Ensure-Dir $wkOut

  $compatDate = (Get-Date).ToUniversalTime().ToString("yyyy-MM-dd")

  $ownerFinal = $Owner
  if ([string]::IsNullOrWhiteSpace($ownerFinal)) { $ownerFinal = "__OWNER__" }

  $repoFinal = $Repo
  if ([string]::IsNullOrWhiteSpace($repoFinal)) { $repoFinal = $Domain }

  $wrPath = Join-Path $wkTpl "wrangler.toml"
  if (Test-Path -LiteralPath $wrPath) {
    $wr = Read-Text $wrPath
    $wr = $wr.Replace("__DOMAIN__", $Domain).
            Replace("__OWNER__", $ownerFinal).
            Replace("__REPO__", $repoFinal).
            Replace("__COMPAT_DATE__", $compatDate)
    Write-Text (Join-Path $wkOut "wrangler.toml") $wr
  }

  $pkgPath = Join-Path $wkTpl "package.json"
  if (Test-Path -LiteralPath $pkgPath) {
    Copy-Item -Force -LiteralPath $pkgPath -Destination (Join-Path $wkOut "package.json")
  }

  $gitignorePath = Join-Path $wkTpl ".gitignore"
  if (Test-Path -LiteralPath $gitignorePath) {
    Copy-Item -Force -LiteralPath $gitignorePath -Destination (Join-Path $wkOut ".gitignore")
  }

  $srcDir = Join-Path $wkTpl "src"
  if (Test-Path -LiteralPath $srcDir) {
    $dstSrc = Join-Path $wkOut "src"

    if (Test-Path -LiteralPath $dstSrc) {
      Remove-Item -Recurse -Force -LiteralPath $dstSrc
    }

    Ensure-Dir $dstSrc
    Copy-Item -Recurse -Force -Path (Join-Path $srcDir "*") -Destination $dstSrc
  }

  # Replace placeholders inside worker files
  $wkFiles = Get-ChildItem -Recurse -File -LiteralPath $wkOut
  foreach ($f in $wkFiles) {
    try {
      $txt = Get-Content -LiteralPath $f.FullName -Raw -ErrorAction Stop
      $txt = $txt.Replace("__DOMAIN__", $Domain).
              Replace("__OWNER__", $ownerFinal).
              Replace("__REPO__", $repoFinal).
              Replace("__COMPAT_DATE__", $compatDate)
      Set-Content -LiteralPath $f.FullName -Value $txt -Encoding UTF8
    } catch { }
  }

  Write-Host "OK: Worker template copied to $wkOut"
}
