#!/usr/bin/env bash
# SFU MSA Platform — production release helper. Fail-closed; one phase per invocation.
#
# Run from the backend root on the production host. Release order (see the Phase 29 runbook):
#   1. preflight                       read-only checks; safe to repeat
#   2. off-server backup + restore test (operator, outside this script) -> evidence file
#   3. php artisan down                (operator)
#   4. migrate                         needs BACKUP_EVIDENCE and maintenance mode
#   5. caches                          rebuilds compiled config/events/routes/views
#   6. verify-assets <public_html>     checks the published frontend build
#   7. verify                          readiness check + no pending migrations
#   8. php artisan up, smoke tests     (operator)
#   rollback-help                      prints what can and cannot be rolled back
#
# This script never generates or replaces APP_KEY: encrypted data (Academy answers,
# queued jobs, sessions) is readable only with the original key.
# NEVER run migrate:fresh / migrate:refresh / migrate:rollback / db:wipe on production.
# It does not install dependencies (upload vendor/ built for PHP 8.2) or copy frontend files
# (cPanel deployment publishes frontend/dist).

set -Eeuo pipefail
umask 077

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PHP_BIN="${PHP_BIN:-php}"
BACKUP_MAX_AGE_HOURS="${BACKUP_MAX_AGE_HOURS:-24}"
PHASE="${1:-}"
LOG_DIR="$ROOT_DIR/storage/logs"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/release-$(date -u +%Y%m%dT%H%M%SZ)-${PHASE:-none}.log"
: >"$LOG_FILE"

EXIT_USAGE=2
EXIT_UNSAFE_ENV=3
EXIT_APP_KEY=4
EXIT_DATABASE=5
EXIT_BACKUP=6
EXIT_MAINTENANCE=7
EXIT_READINESS=8
EXIT_MIGRATION=9
EXIT_CACHE=10
EXIT_ASSETS=11

log() { printf '%s\n' "$*" | tee -a "$LOG_FILE"; }

fail() {
    local code="$1"
    shift
    printf 'FAIL: %s\n' "$*" | tee -a "$LOG_FILE" >&2
    recovery_hint "$code" >&2
    printf 'Status: FAILED (exit %s). Log: %s\n' "$code" "$LOG_FILE" >&2
    exit "$code"
}

recovery_hint() {
    case "$1" in
        "$EXIT_APP_KEY")
            echo "Recovery: restore the ORIGINAL APP_KEY from the offline key backup into .env. Do not generate a new key." ;;
        "$EXIT_DATABASE")
            echo "Recovery: check DB_* settings in .env and that the database server accepts connections. Details (may include host/user, never the password) are in the log." ;;
        "$EXIT_BACKUP")
            echo "Recovery: take a fresh off-server backup, restore it into an isolated database, verify it, then write the evidence file. Migrations stay blocked until then." ;;
        "$EXIT_MIGRATION")
            echo "Recovery: keep maintenance mode ON. Read the log for the failing migration, fix its cause and re-run this phase; completed migrations are recorded and will not re-run."
            echo "Do NOT run migrate:rollback (the job_logs scrub cannot be undone). If the schema cannot be fixed forward, restore the verified backup into a NEW database and repoint .env." ;;
        "$EXIT_CACHE")
            echo "Recovery: run 'php artisan config:clear && php artisan route:clear && php artisan event:clear && php artisan view:clear' so the app runs uncached, fix the reported error, then re-run this phase." ;;
        "$EXIT_ASSETS")
            echo "Recovery: rebuild the frontend from the release commit and redeploy it; keep maintenance mode ON or restore the previous public_html copy." ;;
        *) ;;
    esac
}

on_unexpected_error() {
    local status=$?
    printf 'FAIL: unexpected error (status %s) at line %s during phase "%s".\n' "$status" "$1" "$PHASE" | tee -a "$LOG_FILE" >&2
    printf 'Status: FAILED. Log: %s\n' "$LOG_FILE" >&2
    exit 1
}
trap 'on_unexpected_error $LINENO' ERR

artisan() { "$PHP_BIN" artisan "$@" --no-ansi; }

# Every artisan output passes through redact before reaching the terminal or the log: database
# and mail errors can echo configuration values.
SECRETS_FILE=""
trap 'if [[ -n "$SECRETS_FILE" ]]; then rm -f "$SECRETS_FILE"; fi' EXIT

load_secrets() {
    SECRETS_FILE="$(mktemp)"
    { grep -E '^[A-Z0-9_]*(PASSWORD|SECRET|TOKEN|KEY)[A-Z0-9_]*=' .env || true; } \
        | cut -d= -f2- | tr -d '\r' | sed -E "s/^\"(.*)\"$/\1/; s/^'(.*)'$/\1/" \
        | awk 'length($0) >= 6' >"$SECRETS_FILE"
}

redact() {
    awk -v f="$SECRETS_FILE" '
        BEGIN { if (f != "") while ((getline s < f) > 0) secrets[++n] = s }
        {
            line = $0
            for (k = 1; k <= n; k++) {
                s = secrets[k]
                while ((i = index(line, s)) > 0) line = substr(line, 1, i - 1) "[REDACTED]" substr(line, i + length(s))
            }
            print line
        }'
}

# Reads KEY from .env without sourcing it (sourcing would execute the file).
env_value() {
    local line
    line="$(grep -E "^$1=" .env 2>/dev/null | tail -n 1 || true)"
    line="${line#*=}"
    line="${line%$'\r'}"
    line="${line%\"}"
    line="${line#\"}"
    line="${line%\'}"
    line="${line#\'}"
    printf '%s' "$line"
}

sha256_of() { printf '%s' "$1" | sha256sum | awk '{print $1}'; }

check_environment() {
    [[ -f .env ]] || fail "$EXIT_UNSAFE_ENV" ".env not found. Create it by hand from the private production configuration; this script never copies or generates it."
    load_secrets
    [[ "$(env_value APP_ENV)" == "production" ]] || fail "$EXIT_UNSAFE_ENV" "APP_ENV must be production."
    [[ "$(env_value APP_DEBUG)" == "false" ]] || fail "$EXIT_UNSAFE_ENV" "APP_DEBUG must be false in production."
    [[ "$(env_value APP_URL)" == https://* ]] || fail "$EXIT_UNSAFE_ENV" "APP_URL must use https://."
    local queue
    queue="$(env_value QUEUE_CONNECTION)"
    [[ -n "$queue" && "$queue" != "sync" ]] || fail "$EXIT_UNSAFE_ENV" "QUEUE_CONNECTION=sync (or unset) is unsafe in production. Use database."
    [[ -f vendor/autoload.php ]] || fail "$EXIT_UNSAFE_ENV" "vendor/ is missing. Upload the vendor/ archive built with 'composer install --no-dev' for PHP 8.2."
    if [[ -d vendor/phpunit ]]; then
        log "WARN: vendor/phpunit present — vendor/ looks like a development install."
    fi
    log "Environment: APP_ENV=production, APP_DEBUG=false, APP_URL https, QUEUE_CONNECTION=$queue"
}

check_app_key() {
    local key decoded_length
    key="$(env_value APP_KEY)"
    [[ -n "$key" ]] || fail "$EXIT_APP_KEY" "APP_KEY is missing or blank. Refusing to continue: a new key would make existing encrypted data unreadable."
    if [[ "$key" == base64:* ]]; then
        decoded_length="$({ printf '%s' "${key#base64:}" | base64 -d 2>/dev/null || true; } | wc -c | tr -d ' ')"
        [[ "$decoded_length" == "32" ]] || fail "$EXIT_APP_KEY" "APP_KEY is malformed (expected base64 of 32 bytes)."
    else
        [[ "${#key}" == "32" ]] || fail "$EXIT_APP_KEY" "APP_KEY is malformed (expected base64: prefix or 32 characters)."
    fi
    APP_KEY_SHA256="$(sha256_of "$key")"
    log "APP_KEY present; fingerprint sha256:${APP_KEY_SHA256:0:12}… (record this, never the key itself)"
}

check_database() {
    if ! artisan migrate:status 2>&1 | redact >>"$LOG_FILE"; then
        fail "$EXIT_DATABASE" "Cannot reach the database or read the migrations table."
    fi
    PENDING_MIGRATIONS="$(artisan migrate:status 2>/dev/null | grep -E 'Pending' | awk '{print $1}' || true)"
    local count=0
    [[ -n "$PENDING_MIGRATIONS" ]] && count="$(printf '%s\n' "$PENDING_MIGRATIONS" | wc -l | tr -d ' ')"
    log "Database reachable. Pending migrations: $count"
    if [[ -n "$PENDING_MIGRATIONS" ]]; then
        printf '%s\n' "$PENDING_MIGRATIONS" | sed 's/^/  - /' | tee -a "$LOG_FILE"
    fi
}

run_readiness_check() {
    if ! artisan msa:production-check --strict 2>&1 | redact | tee -a "$LOG_FILE"; then
        fail "$EXIT_READINESS" "msa:production-check --strict reported failures. Fix them; they cannot be skipped."
    fi
}

evidence_value() { { grep -E "^$1=" "$BACKUP_EVIDENCE" || true; } | tail -n 1 | cut -d= -f2- | tr -d '\r'; }

check_backup_evidence() {
    [[ -n "${BACKUP_EVIDENCE:-}" ]] || fail "$EXIT_BACKUP" "BACKUP_EVIDENCE is not set. Migrations require evidence of a restore-tested backup."
    [[ -f "$BACKUP_EVIDENCE" ]] || fail "$EXIT_BACKUP" "Backup evidence file not found."

    local field
    for field in backup_sha256 backup_location restore_verified restore_database restore_checked_at restore_table_count app_key_sha256 storage_backup_sha256 operator; do
        [[ -n "$(evidence_value "$field")" ]] || fail "$EXIT_BACKUP" "Backup evidence is missing '$field'."
    done

    [[ "$(evidence_value restore_verified)" == "yes" ]] || fail "$EXIT_BACKUP" "Backup evidence does not record a successful restore (restore_verified=yes)."
    [[ "$(evidence_value backup_sha256)" =~ ^[0-9a-f]{64}$ ]] || fail "$EXIT_BACKUP" "backup_sha256 must be a 64-character lowercase hex digest."
    [[ "$(evidence_value storage_backup_sha256)" =~ ^[0-9a-f]{64}$ ]] || fail "$EXIT_BACKUP" "storage_backup_sha256 must be a 64-character lowercase hex digest."
    [[ "$(evidence_value restore_table_count)" =~ ^[1-9][0-9]*$ ]] || fail "$EXIT_BACKUP" "restore_table_count must be a positive number."
    [[ "$(evidence_value restore_database)" != "$(env_value DB_DATABASE)" ]] || fail "$EXIT_BACKUP" "The restore test must use an isolated database, not the production database."
    [[ "$(evidence_value app_key_sha256)" == "$APP_KEY_SHA256" ]] || fail "$EXIT_BACKUP" "APP_KEY fingerprint differs from the one recorded with the backup. The key changed; encrypted data in the backup would be unreadable."

    local checked_at now age_hours
    checked_at="$(date -u -d "$(evidence_value restore_checked_at)" +%s 2>/dev/null || true)"
    [[ "$checked_at" =~ ^[0-9]+$ ]] || fail "$EXIT_BACKUP" "restore_checked_at is not a valid timestamp (use ISO 8601 UTC)."
    now="$(date -u +%s)"
    (( checked_at <= now + 300 )) || fail "$EXIT_BACKUP" "restore_checked_at is in the future."
    age_hours=$(( (now - checked_at) / 3600 ))
    (( age_hours < BACKUP_MAX_AGE_HOURS )) || fail "$EXIT_BACKUP" "The restore test is ${age_hours}h old (limit ${BACKUP_MAX_AGE_HOURS}h). Take and verify a fresh backup."

    if [[ -n "${BACKUP_FILE:-}" ]]; then
        [[ -f "$BACKUP_FILE" ]] || fail "$EXIT_BACKUP" "BACKUP_FILE is set but not found."
        [[ "$(sha256sum "$BACKUP_FILE" | awk '{print $1}')" == "$(evidence_value backup_sha256)" ]] || fail "$EXIT_BACKUP" "BACKUP_FILE checksum does not match backup_sha256."
        log "Backup file checksum matches the evidence."
    fi

    log "Backup evidence accepted (restore tested ${age_hours}h ago into an isolated database by $(evidence_value operator))."
    log "Note: this is the operator's attestation; the script cannot itself prove the restore happened."
}

phase_preflight() {
    check_environment
    check_app_key
    check_database
    run_readiness_check
    log "Preflight: PASSED (nothing was changed)."
}

phase_migrate() {
    check_environment
    check_app_key
    check_backup_evidence
    [[ -f storage/framework/down ]] || fail "$EXIT_MAINTENANCE" "Maintenance mode is off. Run 'php artisan down' first; migrations run only while the app is down."
    check_database
    if [[ -z "$PENDING_MIGRATIONS" ]]; then
        log "Migrate: nothing pending."
        return
    fi
    log "Running: php artisan migrate --force"
    if ! artisan migrate --force --no-interaction 2>&1 | redact | tee -a "$LOG_FILE"; then
        artisan migrate:status 2>&1 | redact >>"$LOG_FILE" || log "WARN: could not record migrate:status after the failure."
        fail "$EXIT_MIGRATION" "Migration failed. The application is still in maintenance mode."
    fi
    log "Migrate: PASSED. Maintenance mode is still ON."
}

phase_caches() {
    check_environment
    check_app_key
    # Deliberately not optimize:clear: it also runs cache:clear, wiping rate-limit counters,
    # scheduler overlap locks and the announcement email sender lock.
    local step
    for step in config:clear event:clear route:clear view:clear config:cache event:cache route:cache view:cache; do
        if ! artisan "$step" 2>&1 | redact >>"$LOG_FILE"; then
            fail "$EXIT_CACHE" "'php artisan $step' failed."
        fi
    done
    log "Caches: rebuilt (config, events, routes, views)."
}

phase_verify_assets() {
    local dir="${1:-}"
    [[ -n "$dir" && -d "$dir" ]] || fail "$EXIT_USAGE" "Usage: $0 verify-assets <published frontend directory>"
    [[ -f "$dir/index.html" ]] || fail "$EXIT_ASSETS" "index.html missing from $dir."

    local ref missing=0
    while IFS= read -r ref; do
        [[ -f "$dir/$ref" ]] || { log "missing asset: $ref"; missing=1; }
    done < <({ grep -oE '(src|href)="/assets/[^"]+"' "$dir/index.html" || true; } | sed -E 's/^(src|href)="\///; s/"$//')
    (( missing == 0 )) || fail "$EXIT_ASSETS" "index.html references assets that are not published."

    [[ -f "$dir/.htaccess" ]] || fail "$EXIT_ASSETS" ".htaccess missing (SPA routing and security headers)."
    grep -q 'X-Content-Type-Options' "$dir/.htaccess" || fail "$EXIT_ASSETS" ".htaccess has no security-header block — the build is stale."
    grep -q 'RewriteRule' "$dir/.htaccess" || fail "$EXIT_ASSETS" ".htaccess has no SPA rewrite rules."

    local exposed
    exposed="$(find "$dir" -type f \( -name '*.map' -o -name '.env*' -o -name '*.log' -o -name '*.sql' -o -name '*.bak' -o -name '*.zip' -o -name '*.tar.gz' \))"
    [[ -z "$exposed" ]] || fail "$EXIT_ASSETS" "Private or debug files are published: ${exposed//$'\n'/, }"

    if grep -rqE 'https?://(localhost|127\.0\.0\.1)(:[0-9]+)?/api' "$dir/assets" 2>/dev/null; then
        fail "$EXIT_ASSETS" "The build points at a development API URL."
    fi
    if [[ -n "${EXPECTED_API_URL:-}" ]] && ! grep -rqF "$EXPECTED_API_URL" "$dir/assets"; then
        fail "$EXIT_ASSETS" "The build does not contain EXPECTED_API_URL."
    fi
    log "Assets: PASSED ($dir)."
}

phase_verify() {
    check_environment
    check_app_key
    check_database
    [[ -z "$PENDING_MIGRATIONS" ]] || fail "$EXIT_MIGRATION" "Migrations are still pending."
    run_readiness_check
    artisan queue:restart 2>&1 | redact >>"$LOG_FILE" || log "WARN: queue:restart failed (workers will pick up new code on their next cron start)."
    if [[ -f storage/framework/down ]]; then
        log "Maintenance mode is ON. Bring the app up with 'php artisan up' when ready for smoke tests."
    fi
    log "Verify: PASSED."
}

phase_rollback_help() {
    cat <<'EOF'
Rollback boundaries:
  - Before 'migrate' ran: redeploy the previous code tree, rebuild caches, 'php artisan up'. No data changed.
  - After 'migrate' ran: roll back CODE ONLY. Do not run migrate:rollback; the job_logs payload
    scrub has no down() and cannot be reversed. The other release migrations are additive or
    widen a column, which the previous release tolerates.
  - Data loss or unreadable encrypted data: restore the verified backup into a NEW database,
    check it, then point DB_DATABASE at it. Anything written after the backup is lost.
  - Never change APP_KEY as a rollback step.
EOF
}

case "$PHASE" in
    preflight) phase_preflight ;;
    migrate) phase_migrate ;;
    caches) phase_caches ;;
    verify-assets) phase_verify_assets "${2:-}" ;;
    verify) phase_verify ;;
    rollback-help) phase_rollback_help ;;
    *)
        echo "Usage: $0 {preflight|migrate|caches|verify-assets <dir>|verify|rollback-help}" >&2
        echo "Production queue/cron settings: see docs/PHASE_29_VERIFICATION_REPORT.md (QUEUE_CONNECTION=sync is refused)." >&2
        exit "$EXIT_USAGE"
        ;;
esac
