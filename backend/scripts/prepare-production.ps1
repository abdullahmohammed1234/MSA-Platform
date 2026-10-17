# SFU MSA Platform — production preparation is NOT supported from PowerShell.
#
# The production host is Linux shared hosting; use scripts/prepare-production.sh there, one phase
# at a time (preflight, migrate, caches, verify-assets, verify). That script refuses to generate
# APP_KEY, requires restore-tested backup evidence and maintenance mode before
# `php artisan migrate --force`, and stops when `php artisan msa:production-check --strict` fails.
# This file previously ran key:generate --force on a blank APP_KEY and ignored readiness failures,
# so it now refuses to run rather than keep a second, divergent copy of the release logic.

Write-Error "prepare-production.ps1 is blocked. Run scripts/prepare-production.sh on the Linux production host (see docs/PHASE_29_VERIFICATION_REPORT.md)." -ErrorAction Continue
exit 1
