# SFU MSA Digital Platform — Deployment Infrastructure Validation Script (PowerShell)
# Verifies .cpanel.yml, frontend/dist, connect/dist, backend configs, and CORS invariants locally.

[CmdletBinding()]
param()

$ErrorActionPreference = "Stop"
$root = Resolve-Path "$PSScriptRoot/.."
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "SFU MSA Platform — Deployment Infrastructure Validator" -ForegroundColor Cyan
Write-Host "Root: $root" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

$passed = 0
$failed = 0

function Assert-Check {
    param([string]$name, [bool]$condition, [string]$detail = "")
    if ($condition) {
        Write-Host " [PASS] $name" -ForegroundColor Green
        $script:passed++
    } else {
        Write-Host " [FAIL] $name : $detail" -ForegroundColor Red
        $script:failed++
    }
}

# --- 1. .cpanel.yml Validation ---
Write-Host "`n--- Checking .cpanel.yml ---" -ForegroundColor Yellow
$cpanelPath = Join-Path $root ".cpanel.yml"
$cpanelExists = Test-Path $cpanelPath
Assert-Check ".cpanel.yml exists" $cpanelExists

if ($cpanelExists) {
    $cpanelContent = Get-Content $cpanelPath -Raw
    Assert-Check ".cpanel.yml defines deployment tasks" ($cpanelContent -match "deployment:\s+tasks:")
    Assert-Check "Contains msa-backend destination" ($cpanelContent -match "/home2/sfums1d5/msa-backend")
    Assert-Check "Contains public_html destination" ($cpanelContent -match "/home2/sfums1d5/public_html")
    Assert-Check "Contains connect.sfumsa.ca destination" ($cpanelContent -match "/home2/sfums1d5/connect.sfumsa.ca")
    Assert-Check "Excludes .env files from sync" ($cpanelContent -match "--exclude='\*?\.env\*'")
    Assert-Check "Excludes source maps from sync" ($cpanelContent -match "--exclude='\*\.map'")
    Assert-Check "Excludes tests from backend sync" ($cpanelContent -match "--exclude='tests'")
    Assert-Check "Executable tasks do NOT contain raw artisan commands" (-not ($cpanelContent -match "^\s*-\s+.*artisan"))
    Assert-Check "Executable tasks do NOT contain raw migrate commands" (-not ($cpanelContent -match "^\s*-\s+.*migrate"))
}

# --- 2. Main Frontend Build Validation (frontend/dist) ---
Write-Host "`n--- Checking frontend/dist ---" -ForegroundColor Yellow
$frontendDist = Join-Path $root "frontend/dist"
Assert-Check "frontend/dist exists" (Test-Path $frontendDist)
Assert-Check "frontend/dist/index.html exists" (Test-Path (Join-Path $frontendDist "index.html"))
Assert-Check "frontend/dist/.htaccess exists" (Test-Path (Join-Path $frontendDist ".htaccess"))
Assert-Check "frontend/dist/robots.txt exists" (Test-Path (Join-Path $frontendDist "robots.txt"))
Assert-Check "frontend/dist/sitemap.xml exists" (Test-Path (Join-Path $frontendDist "sitemap.xml"))

$frontendMaps = Get-ChildItem -Path $frontendDist -Filter "*.map" -Recurse -ErrorAction SilentlyContinue
Assert-Check "No source maps in frontend/dist" ($frontendMaps.Count -eq 0)

$frontendEnvs = Get-ChildItem -Path $frontendDist -Filter ".env*" -Recurse -Force -ErrorAction SilentlyContinue
Assert-Check "No .env files in frontend/dist" ($frontendEnvs.Count -eq 0)

# Check API endpoint in frontend assets
$frontendJsFiles = Get-ChildItem -Path "$frontendDist/assets" -Filter "*.js" -ErrorAction SilentlyContinue
$foundFrontendApiUrl = $false
$foundFrontendDevUrl = $false
foreach ($f in $frontendJsFiles) {
    $content = Get-Content $f.FullName -Raw
    if ($content -match "https://api\.sfumsa\.ca/api/v1") { $foundFrontendApiUrl = $true }
    if ($content -match "http://localhost:8000") { $foundFrontendDevUrl = $true }
}
Assert-Check "frontend/dist targets production API (https://api.sfumsa.ca/api/v1)" $foundFrontendApiUrl
Assert-Check "frontend/dist has NO hardcoded localhost:8000" (-not $foundFrontendDevUrl)

# --- 3. Connect Build Validation (connect/dist) ---
Write-Host "`n--- Checking connect/dist ---" -ForegroundColor Yellow
$connectDist = Join-Path $root "connect/dist"
Assert-Check "connect/dist exists" (Test-Path $connectDist)
Assert-Check "connect/dist/index.html exists" (Test-Path (Join-Path $connectDist "index.html"))
Assert-Check "connect/dist/.htaccess exists" (Test-Path (Join-Path $connectDist ".htaccess"))
Assert-Check "connect/dist/robots.txt exists" (Test-Path (Join-Path $connectDist "robots.txt"))
Assert-Check "connect/dist/sw.js exists" (Test-Path (Join-Path $connectDist "sw.js"))
Assert-Check "connect/dist/manifest.webmanifest exists" (Test-Path (Join-Path $connectDist "manifest.webmanifest"))

$connectMaps = Get-ChildItem -Path $connectDist -Filter "*.map" -Recurse -ErrorAction SilentlyContinue
Assert-Check "No source maps in connect/dist" ($connectMaps.Count -eq 0)

$connectEnvs = Get-ChildItem -Path $connectDist -Filter ".env*" -Recurse -Force -ErrorAction SilentlyContinue
Assert-Check "No .env files in connect/dist" ($connectEnvs.Count -eq 0)

$connectJsFiles = Get-ChildItem -Path "$connectDist/assets" -Filter "*.js" -ErrorAction SilentlyContinue
$foundConnectApiUrl = $false
$foundConnectDevUrl = $false
foreach ($f in $connectJsFiles) {
    $content = Get-Content $f.FullName -Raw
    if ($content -match "https://api\.sfumsa\.ca/api/v1") { $foundConnectApiUrl = $true }
    if ($content -match "http://localhost:8000") { $foundConnectDevUrl = $true }
}
Assert-Check "connect/dist targets production API (https://api.sfumsa.ca/api/v1)" $foundConnectApiUrl
Assert-Check "connect/dist has NO hardcoded localhost:8000" (-not $foundConnectDevUrl)

# --- 4. Backend Configuration & CORS Invariants ---
Write-Host "`n--- Checking Backend Configuration ---" -ForegroundColor Yellow
$corsConfig = Join-Path $root "backend/config/cors.php"
$corsContent = Get-Content $corsConfig -Raw
Assert-Check "backend/config/cors.php sets explicit HTTP methods" ($corsContent -match "'allowed_methods'\s*=>\s*\[\s*'GET'")
Assert-Check "backend/config/cors.php allows Authorization header" ($corsContent -match "'Authorization'")
Assert-Check "backend/config/cors.php allows X-XSRF-TOKEN header" ($corsContent -match "'X-XSRF-TOKEN'")
Assert-Check "backend/config/cors.php supports credentials" ($corsContent -match "'supports_credentials'\s*=>\s*true")

$backendHtaccess = Join-Path $root "backend/public/.htaccess"
$backendHtaccessContent = Get-Content $backendHtaccess -Raw
Assert-Check "backend/public/.htaccess handles Authorization header" ($backendHtaccessContent -match "HTTP_AUTHORIZATION")
Assert-Check "backend/public/.htaccess handles X-XSRF-Token header" ($backendHtaccessContent -match "HTTP_X_XSRF_TOKEN")
Assert-Check "backend/public/.htaccess disables indexes" ($backendHtaccessContent -match "Options.*-Indexes")

# --- Summary ---
Write-Host "`n==========================================================" -ForegroundColor Cyan
Write-Host "Validation Summary: $passed Passed, $failed Failed" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Red" })
Write-Host "==========================================================" -ForegroundColor Cyan

if ($failed -gt 0) {
    exit 1
} else {
    exit 0
}
