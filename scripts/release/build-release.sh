#!/usr/bin/env bash
# SFU MSA Platform — reproducible release builder. Fail-closed; never deploys anything.
#
# Usage:
#   build-release.sh --ref <commit|tag> --out <dir>   releasable: exports a committed revision (git archive)
#   build-release.sh --worktree --out <dir>           local candidate from the working tree; NOT releasable
#
# Produces <dir>/ in the .cpanel.yml layout (msa-backend/, public_html/), RELEASE_MANIFEST.txt,
# SHA256SUMS, then <dir>.tar.gz and <dir>.tar.gz.sha256. Every step must succeed or the build stops
# and nothing is left in <dir>.
#
# Backend: environment files are stripped, dependencies come from composer.lock via
# 'composer install --no-dev', tests and runtime files are dropped. Frontend: 'npm ci' and the
# canonical 'npm run build' (vue-tsc + vite, production mode) into a fresh directory, so a stale
# checked-in frontend/dist can never be shipped. The result must pass validate-release.sh.
#
# Env: PHP_BIN, COMPOSER_BIN, NPM_BIN, EXPECTED_API_URL (default https://api.sfumsa.ca/api/v1),
#      MIN_PHP_VERSION (default 8.2.0).
# Exit codes: 2 usage, 30 missing tool, 31 PHP version, 32 source revision, 33 composer install,
#             34 composer.lock invalid, 35 frontend build, 36 artifact validation.

set -Eeuo pipefail
umask 022

EXIT_USAGE=2
EXIT_TOOL=30
EXIT_PHP=31
EXIT_SOURCE=32
EXIT_COMPOSER=33
EXIT_LOCK=34
EXIT_FRONTEND=35
EXIT_VALIDATION=36

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
EXPECTED_API_URL="${EXPECTED_API_URL:-https://api.sfumsa.ca/api/v1}"
MIN_PHP_VERSION="${MIN_PHP_VERSION:-8.2.0}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

STAGE=""
OUT=""
cleanup() {
    local status=$?
    [[ -n "$STAGE" && -d "$STAGE" ]] && rm -rf "$STAGE"
    if (( status != 0 )) && [[ -n "$OUT" ]]; then
        rm -rf "$OUT" "$OUT.tar.gz" "$OUT.tar.gz.sha256"
    fi
}
trap cleanup EXIT

fail() {
    local code="$1"
    shift
    printf 'FAIL: %s\n' "$*" >&2
    printf 'Status: FAILED (exit %s). No artifact was produced.\n' "$code" >&2
    exit "$code"
}
step() { printf '==> %s\n' "$*"; }
on_unexpected_error() {
    local status=$? line="$1"
    printf 'FAIL: unexpected error (line %s, status %s).\n' "$line" "$status" >&2
    printf 'Status: FAILED (exit %s). No artifact was produced.\n' "$status" >&2
    exit "$status"
}
trap 'on_unexpected_error $LINENO' ERR

MODE=""
REF=""
while (( $# > 0 )); do
    case "$1" in
        --ref) MODE=release; REF="${2:-}"; shift; (( $# > 0 )) && shift ;;
        --worktree) MODE=candidate; shift ;;
        --out) OUT="${2:-}"; shift; (( $# > 0 )) && shift ;;
        *) fail "$EXIT_USAGE" "Unknown argument '$1'. Usage: $0 (--ref <commit|tag> | --worktree) --out <dir>" ;;
    esac
done
[[ -n "$MODE" && -n "$OUT" ]] || { OUT=""; fail "$EXIT_USAGE" "Usage: $0 (--ref <commit|tag> | --worktree) --out <dir>"; }
[[ "$MODE" != release || -n "$REF" ]] || { OUT=""; fail "$EXIT_USAGE" "--ref needs a commit or tag."; }
if [[ -e "$OUT" || -e "$OUT.tar.gz" ]]; then
    OUT=""
    fail "$EXIT_USAGE" "Output already exists; refusing to overwrite a previous artifact."
fi
mkdir -p "$(dirname "$OUT")"
OUT="$(cd "$(dirname "$OUT")" && pwd)/$(basename "$OUT")"

for tool in git tar sha256sum "$PHP_BIN" "$COMPOSER_BIN" "$NPM_BIN"; do
    command -v "$tool" >/dev/null 2>&1 || fail "$EXIT_TOOL" "Required tool not found: $tool"
done

PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || true)"
"$PHP_BIN" -r "exit(version_compare(PHP_VERSION, '$MIN_PHP_VERSION', '>=') ? 0 : 1);" 2>/dev/null \
    || fail "$EXIT_PHP" "PHP ${PHP_VERSION:-unknown} is older than $MIN_PHP_VERSION."

REPO_ROOT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null)" || fail "$EXIT_SOURCE" "Not inside a git repository."
STAGE="$(mktemp -d "$(dirname "$OUT")/.stage-XXXXXX")"
mkdir -p "$STAGE/src" "$STAGE/dist"

# --- source --------------------------------------------------------------------
if [[ "$MODE" == release ]]; then
    COMMIT="$(git -C "$REPO_ROOT" rev-parse --verify --quiet "${REF}^{commit}")" || fail "$EXIT_SOURCE" "Unknown revision '$REF'."
    step "Exporting committed revision $COMMIT"
    git -C "$REPO_ROOT" archive --format=tar "$COMMIT" backend frontend | tar -x -C "$STAGE/src" \
        || fail "$EXIT_SOURCE" "git archive failed."
    DIRTY="n/a (committed revision)"
    RELEASABLE="yes (subject to review and authorization)"
else
    COMMIT="$(git -C "$REPO_ROOT" rev-parse HEAD)"
    step "Copying the working tree (tracked + untracked, honouring .gitignore) on top of $COMMIT"
    (
        cd "$REPO_ROOT"
        git ls-files -z -c -o --exclude-standard -- backend frontend \
            | while IFS= read -r -d '' f; do [[ -f "$f" ]] && printf '%s\0' "$f"; done \
            | tar --null -T - -cf -
    ) | tar -x -C "$STAGE/src" || fail "$EXIT_SOURCE" "Copying the working tree failed."
    if [[ -n "$(git -C "$REPO_ROOT" status --porcelain -- backend frontend)" ]]; then
        DIRTY="yes (uncommitted changes included)"
    else
        DIRTY="no"
    fi
    RELEASABLE="NO - built from a working tree; rebuild with --ref <reviewed commit> for release"
fi
[[ -f "$STAGE/src/backend/artisan" && -f "$STAGE/src/frontend/package.json" ]] || fail "$EXIT_SOURCE" "Source export is incomplete."

# --- strip environment files before anything else runs ---------------------------
STRIPPED="$(cd "$STAGE/src/backend" && find . \( -name '.env' -o -name '.env.*' \) ! -name '.env.example' -type f | sed 's#^\./##' | sort)"
if [[ -n "$STRIPPED" ]]; then
    step "Stripping environment files from the backend: ${STRIPPED//$'\n'/, }"
    (cd "$STAGE/src/backend" && printf '%s\n' "$STRIPPED" | while IFS= read -r f; do rm -f -- "$f"; done)
fi

# --- backend dependencies ---------------------------------------------------------
B="$STAGE/src/backend"
[[ -f "$B/composer.lock" ]] || fail "$EXIT_LOCK" "composer.lock is missing; dependencies cannot be reproduced."
step "Validating composer.json / composer.lock"
validate_out="$(cd "$B" && "$COMPOSER_BIN" validate --no-check-publish --no-check-all --no-ansi 2>&1)" || true
if grep -qiE 'lock file is not up to date|not a valid json|does not match the expected json schema' <<<"$validate_out"; then
    fail "$EXIT_LOCK" "composer.lock does not match composer.json; regenerate and review it before release."
fi

step "composer install --no-dev (locked versions)"
(cd "$B" && "$COMPOSER_BIN" install --no-dev --no-interaction --no-progress --prefer-dist \
    --optimize-autoloader --no-scripts --no-ansi) >"$STAGE/composer.log" 2>&1 \
    || { tail -n 20 "$STAGE/composer.log" >&2; fail "$EXIT_COMPOSER" "composer install --no-dev failed."; }
(cd "$B" && "$COMPOSER_BIN" check-platform-reqs --no-dev --no-ansi) >"$STAGE/platform.log" 2>&1 \
    || { cat "$STAGE/platform.log" >&2; fail "$EXIT_COMPOSER" "This build machine does not satisfy the locked platform requirements."; }
step "Package discovery (replaces the skipped post-autoload-dump script)"
(cd "$B" && env -u DB_URL APP_ENV=production APP_DEBUG=false DB_CONNECTION=sqlite DB_DATABASE=:memory: \
    "$PHP_BIN" artisan package:discover --no-ansi) >"$STAGE/discover.log" 2>&1 \
    || { tail -n 20 "$STAGE/discover.log" >&2; fail "$EXIT_COMPOSER" "artisan package:discover failed."; }

step "Removing tests, local databases and runtime files"
rm -rf "$B/tests" "$B/node_modules" "$B/.phpunit.cache" "$B/.phpunit.result.cache"
find "$B" -type d \( -name '.git' -o -name '.github' \) -prune -exec rm -rf {} + 2>/dev/null || true
rm -f "$B"/phpunit*.xml
if [[ -d "$B/database" ]]; then
    find "$B/database" -maxdepth 1 -type f \( -name '*.sqlite' -o -name '*.sqlite3' -o -name '*.db' \) -delete
fi
find "$B" -maxdepth 1 -type f ! -name '.env.example' ! -name '.gitignore' ! -name '.editorconfig' ! -name '.gitattributes' \
    -exec sh -c 'head -c 15 "$1" 2>/dev/null | grep -q "SQLite format 3"' _ {} \; -delete
find "$B/storage" -type f ! -name '.gitignore' -delete
find "$B/bootstrap/cache" -type f ! -name '.gitignore' ! -name 'packages.php' ! -name 'services.php' -delete
rm -rf "$B/public/storage"
mkdir -p "$B/storage/app/public" "$B/storage/framework/cache/data" "$B/storage/framework/sessions" \
    "$B/storage/framework/views" "$B/storage/logs" "$B/bootstrap/cache"

# --- frontend ---------------------------------------------------------------------
F="$STAGE/src/frontend"
rm -rf "$F/dist"
[[ -f "$F/package-lock.json" ]] || fail "$EXIT_FRONTEND" "frontend/package-lock.json is missing."
step "npm ci (locked versions)"
(cd "$F" && "$NPM_BIN" ci --no-audit --no-fund) >"$STAGE/npm-ci.log" 2>&1 \
    || { tail -n 20 "$STAGE/npm-ci.log" >&2; fail "$EXIT_FRONTEND" "npm ci failed."; }
step "npm run build (vue-tsc + vite, production mode)"
(cd "$F" && "$NPM_BIN" run build -- --outDir "$STAGE/dist" --emptyOutDir) >"$STAGE/npm-build.log" 2>&1 \
    || { tail -n 20 "$STAGE/npm-build.log" >&2; fail "$EXIT_FRONTEND" "Frontend build failed."; }
[[ -f "$STAGE/dist/index.html" ]] || fail "$EXIT_FRONTEND" "The build produced no index.html."

# --- assemble ---------------------------------------------------------------------
step "Assembling $OUT"
mkdir -p "$OUT/msa-backend" "$OUT/public_html"
(cd "$B" && tar -cf - .) | tar -x -C "$OUT/msa-backend"
(cd "$STAGE/dist" && tar -cf - .) | tar -x -C "$OUT/public_html"

sha_of() { sha256sum "$1" | cut -d' ' -f1; }
{
    echo "SFU MSA Platform release artifact"
    echo "mode: $MODE"
    echo "releasable: $RELEASABLE"
    echo "source_commit: $COMMIT"
    [[ "$MODE" == release ]] && echo "source_ref: $REF"
    echo "working_tree_dirty: $DIRTY"
    echo "built_at_utc: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "php: $PHP_VERSION"
    echo "composer: $("$COMPOSER_BIN" --version --no-ansi 2>/dev/null | head -n 1)"
    echo "node: $(node --version 2>/dev/null || echo unknown)"
    echo "npm: $("$NPM_BIN" --version 2>/dev/null || echo unknown)"
    echo "composer_lock_sha256: $(sha_of "$OUT/msa-backend/composer.lock")"
    echo "package_lock_sha256: $(sha_of "$F/package-lock.json")"
    echo "expected_api_url: $EXPECTED_API_URL"
    echo "stripped_env_files: ${STRIPPED:-none}" | tr '\n' ' '
    echo
    echo "host_steps: composer check-platform-reqs --no-dev on the host PHP; php artisan storage:link; prepare-production.sh phases"
} >"$OUT/RELEASE_MANIFEST.txt"
(cd "$OUT" && find . -type f ! -path ./SHA256SUMS -print0 | LC_ALL=C sort -z | xargs -0 sha256sum) >"$OUT/SHA256SUMS"

step "Validating the artifact"
EXPECTED_API_URL="$EXPECTED_API_URL" bash "$SCRIPT_DIR/validate-release.sh" "$OUT" \
    || fail "$EXIT_VALIDATION" "The assembled artifact failed validation."

tar -czf "$OUT.tar.gz" -C "$OUT" .
(cd "$(dirname "$OUT")" && sha256sum "$(basename "$OUT").tar.gz") >"$OUT.tar.gz.sha256"
echo "Artifact: $OUT.tar.gz"
echo "SHA-256: $(cut -d' ' -f1 "$OUT.tar.gz.sha256")"
echo "Releasable: $RELEASABLE"
