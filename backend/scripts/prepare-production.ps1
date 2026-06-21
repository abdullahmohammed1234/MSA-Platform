# Phase 1 — Prepare Laravel for production.
# Run from the backend root on the production server after copying .env.production to .env.

$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")

if (-not (Test-Path ".env")) {
    if (Test-Path ".env.production") {
        Copy-Item ".env.production" ".env"
        Write-Host "Copied .env.production to .env"
    } else {
        Write-Error ".env not found. Create .env from .env.production first."
    }
}

$envContent = Get-Content ".env" -Raw
if ($envContent -notmatch "(?m)^APP_DEBUG=false") {
    Write-Error "APP_DEBUG must be false in production."
}

if ($envContent -match "(?m)^APP_KEY=\s*$" -or $envContent -notmatch "(?m)^APP_KEY=") {
    php artisan key:generate --force
    Write-Host "Generated APP_KEY"
}

composer install --optimize-autoloader --no-dev --no-ansi --no-interaction

php artisan migrate --force --no-interaction
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

Write-Host "Production preparation complete."
