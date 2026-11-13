# Sync shared typography assets into each independently deployed app.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$srcFonts = Join-Path $root "shared\typography\fonts"
$srcCss = Join-Path $root "shared\typography\msa-typography.css"

$targets = @(
  "frontend\public\fonts",
  "connect\public\fonts",
  "projects\public\fonts",
  "backend\public\fonts"
)

if (-not (Test-Path $srcCss)) {
  throw "Missing canonical CSS: $srcCss"
}
if (-not (Test-Path $srcFonts)) {
  throw "Missing fonts directory: $srcFonts"
}

foreach ($rel in $targets) {
  $dest = Join-Path $root $rel
  New-Item -ItemType Directory -Force -Path $dest | Out-Null
  Copy-Item (Join-Path $srcFonts "*") $dest -Force
  Copy-Item $srcCss (Join-Path $dest "msa-typography.css") -Force
  Write-Host "Synced -> $dest"
}

Write-Host "Done."
