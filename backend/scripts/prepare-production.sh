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

if grep -qE '^APP_ENV=production' .env; then
  if grep -qE '^QUEUE_CONNECTION=sync' .env; then
    echo "Error: QUEUE_CONNECTION=sync is unsafe in production. Use database or redis." >&2
    exit 1
  fi
  if ! grep -qE '^APP_URL=https://' .env; then
    echo "Warning: APP_URL should use https:// in production." >&2
  fi
fi

if grep -q '^APP_KEY=$' .env || ! grep -q '^APP_KEY=' .env; then
  php artisan key:generate --force
  echo "Generated APP_KEY"
fi

composer install --optimize-autoloader --no-dev --no-ansi --no-interaction

# Safe on existing production DBs — never drops archived CMS event tables.
# NEVER run migrate:fresh / migrate:refresh on production.
php artisan migrate --force --no-interaction

php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan msa:production-check || true

echo "Production preparation complete."
echo "Ops required: php artisan storage:link (once);"
echo "  queue:work --queue=ems-payments,ems-notifications,ems-operations,high,default,low;"
echo "  cron: * * * * * php artisan schedule:run;"
echo "  php artisan queue:restart after each deploy;"
echo "  See docs/PRODUCTION_OPERATIONS_CHECKLIST.md"