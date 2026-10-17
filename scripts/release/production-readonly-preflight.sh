#!/usr/bin/env bash
# SFU MSA Platform — READ-ONLY production inventory, for an authorized operator to run on the host.
#
# Usage: production-readonly-preflight.sh <msa-backend dir> <public_html dir> > preflight.txt
#
# Writes nothing on the server: output goes to stdout only. The only artisan commands it runs are
# '--version', 'migrate:status' and 'db:show' (all read-only). It never prints a secret value:
# .env is reported as key names with set/empty state, except an allowlist of non-secret settings;
# APP_KEY is reported only as a sha256 fingerprint prefix. Table sizes and row counts are aggregate
# numbers; no row data is read. Review the output before sharing it.
#
# The FINGERPRINT section lists git blob hashes of the deployed source, so
# identify-production-release.sh can match the deployment to a commit without server-side git.

set -uo pipefail

BACKEND="${1:-}"
PUBLIC="${2:-}"
PHP_BIN="${PHP_BIN:-php}"
if [[ -z "$BACKEND" || -z "$PUBLIC" || ! -f "$BACKEND/artisan" || ! -d "$PUBLIC" ]]; then
    echo "Usage: $0 <msa-backend dir> <public_html dir>" >&2
    exit 2
fi
BACKEND="$(cd "$BACKEND" && pwd)"
PUBLIC="$(cd "$PUBLIC" && pwd)"

PRINTABLE_KEYS=" APP_ENV APP_DEBUG APP_URL FRONTEND_URL CONNECT_URL QUEUE_CONNECTION CACHE_STORE SESSION_DRIVER \
SESSION_SECURE_COOKIE FORCE_HTTPS MAIL_MAILER DB_CONNECTION LOG_CHANNEL LOG_LEVEL SCHEDULER_CRON_INTERVAL_MINUTES \
ANNOUNCEMENT_EMAIL_HOURLY_CAP ANNOUNCEMENT_EMAIL_MIN_INTERVAL_SECONDS EMS_PAYMENTS_ENABLED "

section() { printf '\n== %s\n' "$1"; }
artisan() { (cd "$BACKEND" && "$PHP_BIN" artisan "$@" --no-ansi --no-interaction 2>&1); }
run_or_note() {
    local out status
    out="$("$@" 2>&1)"
    status=$?
    if (( status == 0 )); then printf '%s\n' "$out"; else printf 'UNAVAILABLE (exit %s)\n' "$status"; fi
}

section META
echo "generated_at_utc: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "backend_dir: $BACKEND"
echo "public_dir: $PUBLIC"

section RUNTIME
run_or_note "$PHP_BIN" -r 'echo "php_version: ", PHP_VERSION, "\nphp_sapi: ", PHP_SAPI, "\nmemory_limit: ", ini_get("memory_limit"), "\nmax_execution_time: ", ini_get("max_execution_time"), "\n";'
echo "php_extensions: $("$PHP_BIN" -m 2>/dev/null | grep -vE '^\[|^$' | sort -f | tr '\n' ' ')"
if command -v composer >/dev/null 2>&1; then
    echo "composer: $(composer --version --no-ansi 2>/dev/null | head -n 1)"
else
    echo "composer: not on PATH"
fi
echo "git_on_server: $(command -v git >/dev/null 2>&1 && echo yes || echo no)"

section LARAVEL
run_or_note artisan --version
echo "maintenance_mode: $([[ -f "$BACKEND/storage/framework/down" ]] && echo ON || echo off)"
echo "bootstrap_cache: $(cd "$BACKEND/bootstrap/cache" 2>/dev/null && ls -1 | grep -v '^\.gitignore$' | tr '\n' ' ')"
if [[ -L "$BACKEND/public/storage" ]]; then
    echo "storage_link: symlink -> $(readlink "$BACKEND/public/storage")"
else
    echo "storage_link: $([[ -e "$BACKEND/public/storage" ]] && echo 'present (not a symlink)' || echo missing)"
fi

section "ENVIRONMENT FILES (names only)"
for f in "$BACKEND"/.env*; do
    [[ -e "$f" ]] && echo "present: $(basename "$f")"
done
if [[ -f "$BACKEND/.env" ]]; then
    while IFS= read -r line; do
        [[ "$line" =~ ^[[:space:]]*([A-Za-z_][A-Za-z0-9_]*)[[:space:]]*=(.*)$ ]] || continue
        key="${BASH_REMATCH[1]}"
        value="${BASH_REMATCH[2]}"
        value="${value%%[[:space:]]#*}"
        value="${value#\"}"; value="${value%\"}"; value="${value#\'}"; value="${value%\'}"
        if [[ "$key" == APP_KEY ]]; then
            if [[ -n "$value" ]]; then
                echo "APP_KEY: set, fingerprint sha256:$(printf '%s' "$value" | sha256sum | cut -c1-12)"
            else
                echo "APP_KEY: EMPTY"
            fi
        elif [[ "$PRINTABLE_KEYS" == *" $key "* ]]; then
            echo "$key=$value"
        else
            echo "$key: $([[ -n "$value" ]] && echo set || echo empty)"
        fi
    done < "$BACKEND/.env"
else
    echo ".env: MISSING"
fi

section MIGRATIONS
echo "migration_files: $(find "$BACKEND/database/migrations" -maxdepth 1 -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
run_or_note artisan migrate:status

section DATABASE
dbjson="$(artisan db:show --json --counts)"
if [[ $? -eq 0 ]]; then
    printf '%s' "$dbjson" | "$PHP_BIN" -r '
        $j = json_decode(stream_get_contents(STDIN), true);
        if (! is_array($j)) { echo "UNAVAILABLE (unparseable db:show output)\n"; exit; }
        $p = $j["platform"] ?? [];
        echo "engine: ", $p["name"] ?? "?", "\nversion: ", $p["version"] ?? "?", "\nopen_connections: ", $p["open_connections"] ?? "?", "\n";
        $total = 0;
        foreach ($j["tables"] ?? [] as $t) {
            $total += (int) ($t["size"] ?? 0);
            printf("table %s size_bytes=%s rows=%s\n", $t["table"] ?? "?", $t["size"] ?? "?", $t["rows"] ?? "?");
        }
        echo "total_size_bytes: $total\n";'
else
    echo "UNAVAILABLE"
fi

section STORAGE
run_or_note df -Pk "$BACKEND"
echo "du_kb msa-backend: $(du -sk "$BACKEND" 2>/dev/null | cut -f1)"
echo "du_kb storage/app: $(du -sk "$BACKEND/storage/app" 2>/dev/null | cut -f1)"
echo "du_kb storage/logs: $(du -sk "$BACKEND/storage/logs" 2>/dev/null | cut -f1)"
echo "du_kb public_html: $(du -sk "$PUBLIC" 2>/dev/null | cut -f1)"
echo "storage_app_files: $(find "$BACKEND/storage/app" -type f 2>/dev/null | wc -l | tr -d ' ')"

section CRON
if command -v crontab >/dev/null 2>&1; then
    crontab -l 2>/dev/null | grep -E 'artisan|schedule:run|queue:work' | sed -E 's/(PASSWORD|TOKEN|SECRET|KEY)=[^ ]*/\1=[REDACTED]/g' || echo "no artisan cron entries"
else
    echo "crontab: unavailable"
fi

section "PUBLIC HTML"
echo "htaccess_security_headers: $(grep -c 'Header set' "$PUBLIC/.htaccess" 2>/dev/null || echo 0)"
echo "htaccess_csp_report_only: $(grep -q 'Content-Security-Policy-Report-Only' "$PUBLIC/.htaccess" 2>/dev/null && echo yes || echo no)"
echo "htaccess_dotfile_rule: $(grep -qF '(?!well-known/)' "$PUBLIC/.htaccess" 2>/dev/null && echo yes || echo no)"
echo "asset_files: $(find "$PUBLIC/assets" -type f 2>/dev/null | wc -l | tr -d ' ')"
echo "exposed_files: $(find "$PUBLIC" -maxdepth 3 -type f \( -name '.env*' -o -name '*.map' -o -name '*.sql' -o -name '*.log' -o -name '*.bak' -o -name '*.zip' \) 2>/dev/null | sed "s#^$PUBLIC/##" | tr '\n' ' ')"

section FINGERPRINT
"$PHP_BIN" -r '
    [$self, $root, $label] = array_slice($argv, 0, 3);
    foreach (array_slice($argv, 3) as $rel) {
        $path = "$root/$rel";
        $files = [];
        if (is_file($path)) {
            $files[] = $path;
        } elseif (is_dir($path)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) { if ($f->isFile()) { $files[] = $f->getPathname(); } }
        }
        sort($files);
        foreach ($files as $f) {
            $c = file_get_contents($f);
            $relative = str_replace("\\", "/", substr($f, strlen($root) + 1));
            echo "BLOB ", sha1("blob " . strlen($c) . "\0" . $c), " $label/$relative\n";
        }
    }' "$BACKEND" msa-backend app bootstrap/app.php config routes database/migrations resources/views \
        composer.json composer.lock public/index.php public/.htaccess
"$PHP_BIN" -r '
    [$self, $root, $label] = array_slice($argv, 0, 3);
    $files = [];
    foreach (["index.html", ".htaccess"] as $top) { if (is_file("$root/$top")) { $files[] = "$root/$top"; } }
    if (is_dir("$root/assets")) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/assets", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->isFile()) { $files[] = $f->getPathname(); } }
    }
    sort($files);
    foreach ($files as $f) {
        $c = file_get_contents($f);
        echo "BLOB ", sha1("blob " . strlen($c) . "\0" . $c), " $label/", str_replace("\\", "/", substr($f, strlen($root) + 1)), "\n";
    }' "$PUBLIC" public_html

section END
