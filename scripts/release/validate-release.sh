#!/usr/bin/env bash
# SFU MSA Platform — release artifact validator. Read-only; fail-closed.
#
# Usage: validate-release.sh <artifact-dir>
#
# <artifact-dir> uses the .cpanel.yml layout:
#   msa-backend/   -> /home2/<account>/msa-backend   (Laravel app, vendor built --no-dev)
#   public_html/   -> /home2/<account>/public_html   (frontend production build)
#   SHA256SUMS     optional; verified when present
#
# Optional env: EXPECTED_API_URL (must appear in the built JS).
#
# Exit codes: 2 usage, 20 structure, 21 sensitive file, 22 secret pattern,
#             23 dev dependency, 24 frontend security rule, 25 asset integrity, 26 checksum.

set -Eeuo pipefail

EXIT_USAGE=2
EXIT_STRUCTURE=20
EXIT_SENSITIVE=21
EXIT_SECRET=22
EXIT_DEV_DEPENDENCY=23
EXIT_SECURITY_RULE=24
EXIT_ASSETS=25
EXIT_CHECKSUM=26

fail() {
    local code="$1"
    shift
    printf 'FAIL: %s\n' "$*" >&2
    printf 'Status: FAILED (exit %s). The artifact must not be published.\n' "$code" >&2
    exit "$code"
}

ARTIFACT="${1:-}"
[[ -n "$ARTIFACT" && -d "$ARTIFACT" ]] || fail "$EXIT_USAGE" "Usage: $0 <artifact-dir>"
ARTIFACT="$(cd "$ARTIFACT" && pwd)"
BACKEND="$ARTIFACT/msa-backend"
PUBLIC="$ARTIFACT/public_html"

# --- structure -------------------------------------------------------------
for required in "$BACKEND/artisan" "$BACKEND/public/index.php" "$BACKEND/public/.htaccess" \
    "$BACKEND/vendor/autoload.php" "$BACKEND/composer.json" "$BACKEND/composer.lock" \
    "$BACKEND/bootstrap/app.php" "$PUBLIC/index.html" "$PUBLIC/.htaccess"; do
    [[ -f "$required" ]] || fail "$EXIT_STRUCTURE" "Missing ${required#"$ARTIFACT"/}."
done
for writable in storage/app/public storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache; do
    [[ -d "$BACKEND/$writable" ]] || fail "$EXIT_STRUCTURE" "Missing writable directory msa-backend/$writable."
done
[[ ! -e "$BACKEND/public/storage" ]] || fail "$EXIT_STRUCTURE" "msa-backend/public/storage must not be shipped; create it on the host with 'php artisan storage:link'."

# --- sensitive files -------------------------------------------------------
sensitive="$(find "$ARTIFACT" \
    \( -name '.git' -o -name '.github' -o -name 'node_modules' \) -prune -print -o \
    -type f \( \
        \( -name '.env' -o -name '.env.*' \) ! -name '.env.example' -o \
        -name '*.sql' -o -name '*.sql.gz' -o -name '*.dump' -o \
        -name '*.sqlite' -o -name '*.sqlite3' -o -name '*.db' -o \
        -name '*.log' -o -name '*.bak' -o -name '*.backup' -o -name '*.old' -o -name '*.swp' -o -name '*~' -o \
        -name '*.pem' -o -name '*.key' -o -name '*.p12' -o -name '*.pfx' -o -name '*.ppk' -o \
        -name 'id_rsa*' -o -name 'id_ed25519*' -o -name 'id_ecdsa*' -o \
        -name 'auth.json' -o -name '.phpunit.result.cache' -o -name '*.zip' -o -name '*.tar' -o -name '*.tar.gz' -o -name '*.tgz' \
    \) -print | sed "s#^$ARTIFACT/##")"
[[ -z "$sensitive" ]] || fail "$EXIT_SENSITIVE" "Sensitive or private files in the artifact: ${sensitive//$'\n'/, }"

[[ ! -d "$BACKEND/tests" ]] || fail "$EXIT_SENSITIVE" "Test suite shipped (msa-backend/tests); it holds synthetic fixtures and is not part of the release."
leftover="$(find "$BACKEND/storage" -type f ! -name '.gitignore' | sed "s#^$ARTIFACT/##")"
[[ -z "$leftover" ]] || fail "$EXIT_SENSITIVE" "Runtime files inside storage/: ${leftover//$'\n'/, }"
cachefiles="$(find "$BACKEND/bootstrap/cache" -type f ! -name '.gitignore' ! -name 'packages.php' ! -name 'services.php' | sed "s#^$ARTIFACT/##")"
[[ -z "$cachefiles" ]] || fail "$EXIT_SENSITIVE" "Environment-specific caches shipped (rebuild on the host): ${cachefiles//$'\n'/, }"

# SQLite databases are recognisable by their header whatever they are called.
while IFS= read -r -d '' f; do
    if head -c 16 "$f" 2>/dev/null | grep -qF "SQLite format 3"; then
        fail "$EXIT_SENSITIVE" "SQLite database file in the artifact: ${f#"$ARTIFACT"/}"
    fi
done < <(find "$ARTIFACT" -path "$BACKEND/vendor" -prune -o -type f -size +1k -print0)

# --- secret patterns (application code, config and the public build) -------
secret_regex='-----BEGIN ([A-Z]+ )?PRIVATE KEY-----|sk_live_[A-Za-z0-9]{16,}|EAAA[A-Za-z0-9_-]{40,}|AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{36}|xox[bp]-[A-Za-z0-9-]{10,}|base64:[A-Za-z0-9+/]{43}='
hits="$(grep -rlE --exclude-dir=vendor -e "$secret_regex" "$BACKEND" "$PUBLIC" 2>/dev/null | sed "s#^$ARTIFACT/##" || true)"
[[ -z "$hits" ]] || fail "$EXIT_SECRET" "Credential-shaped strings found in: ${hits//$'\n'/, }"
apphits="$(grep -rlE -e 'base64:[A-Za-z0-9+/]{43}=' "$BACKEND/vendor/composer" 2>/dev/null | sed "s#^$ARTIFACT/##" || true)"
[[ -z "$apphits" ]] || fail "$EXIT_SECRET" "An APP_KEY-shaped value is embedded in: ${apphits//$'\n'/, }"

# --- dev dependencies ------------------------------------------------------
devpkgs="$(sed -n '/"require-dev"[[:space:]]*:/,/}/p' "$BACKEND/composer.json" | sed 's#\\/#/#g' | grep -oE '"[a-z0-9_.-]+/[a-z0-9_.-]+"' | tr -d '"' || true)"
[[ -n "$devpkgs" ]] || ! grep -q '"require-dev"' "$BACKEND/composer.json" || fail "$EXIT_DEV_DEPENDENCY" "Could not read require-dev from msa-backend/composer.json."
for pkg in $devpkgs; do
    [[ ! -d "$BACKEND/vendor/$pkg" ]] || fail "$EXIT_DEV_DEPENDENCY" "Dev dependency $pkg is installed; build with 'composer install --no-dev'."
done
if [[ -f "$BACKEND/vendor/composer/installed.json" ]] && grep -qE '"dev"[[:space:]]*:[[:space:]]*true' "$BACKEND/vendor/composer/installed.json"; then
    fail "$EXIT_DEV_DEPENDENCY" "vendor/composer/installed.json records a dev install."
fi

# --- frontend security rules -----------------------------------------------
ht="$PUBLIC/.htaccess"
grep -qE '^[[:space:]]*Header (always )?set X-Content-Type-Options "nosniff"' "$ht" || fail "$EXIT_SECURITY_RULE" "public_html/.htaccess lacks X-Content-Type-Options."
grep -qE '^[[:space:]]*Header (always )?set X-Frame-Options "SAMEORIGIN"' "$ht" || fail "$EXIT_SECURITY_RULE" "public_html/.htaccess lacks X-Frame-Options."
grep -qE '^[[:space:]]*Header (always )?set Referrer-Policy ' "$ht" || fail "$EXIT_SECURITY_RULE" "public_html/.htaccess lacks Referrer-Policy."
grep -qE '^[[:space:]]*Header (always )?set Content-Security-Policy-Report-Only ' "$ht" || fail "$EXIT_SECURITY_RULE" "public_html/.htaccess lacks the report-only CSP."
if grep -qE '^[[:space:]]*Header (always )?set Content-Security-Policy ' "$ht"; then
    fail "$EXIT_SECURITY_RULE" "public_html/.htaccess enforces CSP; enforcement has not been approved (keep Content-Security-Policy-Report-Only)."
fi
grep -qF 'RewriteRule (^|/)\.(?!well-known/) - [F,L]' "$ht" || fail "$EXIT_SECURITY_RULE" "public_html/.htaccess does not refuse dotfiles while keeping .well-known reachable."
grep -qE 'RewriteRule \\\.\([^)]*map[^)]*\)\$ - \[F' "$ht" || fail "$EXIT_SECURITY_RULE" "public_html/.htaccess does not refuse source maps and backup leftovers."
grep -qE '^[[:space:]]*RewriteRule \. /index\.html \[L\]' "$ht" || fail "$EXIT_SECURITY_RULE" "public_html/.htaccess has no SPA fallback."
grep -q 'RewriteRule \^ index.php \[L\]' "$BACKEND/public/.htaccess" || fail "$EXIT_SECURITY_RULE" "msa-backend/public/.htaccess has no front controller rule."

# --- frontend asset integrity ----------------------------------------------
missing=""
while IFS= read -r ref; do
    [[ -f "$PUBLIC/$ref" ]] || missing+="$ref "
done < <({ grep -oE '(src|href)="/assets/[^"]+"' "$PUBLIC/index.html" || true; } | sed -E 's/^(src|href)="\///; s/"$//')
[[ -z "$missing" ]] || fail "$EXIT_ASSETS" "index.html references unpublished assets: $missing"
grep -qE '(src|href)="/assets/[^"]+\.js"' "$PUBLIC/index.html" || fail "$EXIT_ASSETS" "index.html loads no built JavaScript bundle."
maps="$(find "$PUBLIC" -type f -name '*.map' | sed "s#^$ARTIFACT/##")"
[[ -z "$maps" ]] || fail "$EXIT_ASSETS" "Source maps are published: ${maps//$'\n'/, }"
if grep -rqE 'https?://(localhost|127\.0\.0\.1)(:[0-9]+)?/api' "$PUBLIC/assets" 2>/dev/null; then
    fail "$EXIT_ASSETS" "The build points at a development API URL."
fi
if [[ -n "${EXPECTED_API_URL:-}" ]] && ! grep -rqF "$EXPECTED_API_URL" "$PUBLIC/assets"; then
    fail "$EXIT_ASSETS" "The build does not contain EXPECTED_API_URL."
fi

# --- checksums -------------------------------------------------------------
if [[ -f "$ARTIFACT/SHA256SUMS" ]]; then
    (cd "$ARTIFACT" && sha256sum --quiet --strict -c SHA256SUMS) >/dev/null 2>&1 || fail "$EXIT_CHECKSUM" "SHA256SUMS does not match the artifact contents."
    listed="$(cd "$ARTIFACT" && wc -l < SHA256SUMS | tr -d ' ')"
    actual="$(cd "$ARTIFACT" && find . -type f ! -path ./SHA256SUMS | wc -l | tr -d ' ')"
    [[ "$listed" == "$actual" ]] || fail "$EXIT_CHECKSUM" "SHA256SUMS lists $listed files but the artifact has $actual."
fi

echo "Release artifact: VALID ($ARTIFACT)"
