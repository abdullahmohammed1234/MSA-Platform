#!/usr/bin/env bash
set -euo pipefail

# Phase 1 — Prepare Laravel for production.
# Run from the backend root on the production server after copying .env.production to .env.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -f .env ]]; then
  if [[ -f .env.production ]]; then
    cp .env.production .env
    echo "Copied .env.production to .env"
  else
    echo "Error: .env not found. Create .env from .env.production first." >&2
    exit 1
  fi
fi

if ! grep -q '^APP_DEBUG=false' .env; then
  echo "Error: APP_DEBUG must be false in production." >&2
  exit 1
fi

if grep -q '^APP_KEY=$' .env || ! grep -q '^APP_KEY=' .env; then
  php artisan key:generate --force
  echo "Generated APP_KEY"
fi

composer install --optimize-autoloader --no-dev --no-ansi --no-interaction

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "Production preparation complete."
