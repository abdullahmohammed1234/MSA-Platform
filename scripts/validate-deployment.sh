#!/usr/bin/env bash
# SFU MSA Digital Platform — Deployment Infrastructure Validation Script (Bash)
# Verifies .cpanel.yml, frontend/dist, connect/dist, backend configs, and CORS invariants locally.

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "=========================================================="
echo "SFU MSA Platform — Deployment Infrastructure Validator"
echo "Root: $ROOT"
echo "=========================================================="

PASSED=0
FAILED=0

assert_check() {
    local name="$1"
    local condition="$2"
    local detail="${3:-}"

    if [ "$condition" -eq 0 ]; then
        echo " [PASS] $name"
        PASSED=$((PASSED + 1))
    else
        echo " [FAIL] $name : $detail"
        FAILED=$((FAILED + 1))
    fi
}

# --- 1. .cpanel.yml Validation ---
echo ""
echo "--- Checking .cpanel.yml ---"
CPANEL_PATH="$ROOT/.cpanel.yml"
if [ -f "$CPANEL_PATH" ]; then
    assert_check ".cpanel.yml exists" 0

    grep -q "deployment:" "$CPANEL_PATH" && grep -q "tasks:" "$CPANEL_PATH"
    assert_check ".cpanel.yml defines deployment tasks" $?

    grep -q "/home2/sfums1d5/msa-backend" "$CPANEL_PATH"
    assert_check "Contains msa-backend destination" $?

    grep -q "/home2/sfums1d5/public_html" "$CPANEL_PATH"
    assert_check "Contains public_html destination" $?

    grep -q "/home2/sfums1d5/connect.sfumsa.ca" "$CPANEL_PATH"
    assert_check "Contains connect.sfumsa.ca destination" $?

    grep -q -- "--exclude='.*\.env\*'" "$CPANEL_PATH" || grep -q -- "--exclude='\.env\*'" "$CPANEL_PATH"
    assert_check "Excludes .env files from sync" $?

    grep -q -- "--exclude='\*\.map'" "$CPANEL_PATH"
    assert_check "Excludes source maps from sync" $?

    grep -q -- "--exclude='tests'" "$CPANEL_PATH"
    assert_check "Excludes tests from backend sync" $?

    # Executable task lines starting with "- /" must not contain artisan or migrate
    if grep -E '^\s*-\s+/' "$CPANEL_PATH" | grep -qE '(artisan|migrate)'; then
        assert_check "Executable tasks do NOT contain raw artisan/migrate commands" 1 "Found prohibited command"
    else
        assert_check "Executable tasks do NOT contain raw artisan/migrate commands" 0
    fi
else
    assert_check ".cpanel.yml exists" 1 "File missing"
fi

# --- 2. Main Frontend Build Validation (frontend/dist) ---
echo ""
echo "--- Checking frontend/dist ---"
FRONTEND_DIST="$ROOT/frontend/dist"
[ -d "$FRONTEND_DIST" ]; assert_check "frontend/dist exists" $?
[ -f "$FRONTEND_DIST/index.html" ]; assert_check "frontend/dist/index.html exists" $?
[ -f "$FRONTEND_DIST/.htaccess" ]; assert_check "frontend/dist/.htaccess exists" $?
[ -f "$FRONTEND_DIST/robots.txt" ]; assert_check "frontend/dist/robots.txt exists" $?
[ -f "$FRONTEND_DIST/sitemap.xml" ]; assert_check "frontend/dist/sitemap.xml exists" $?

MAP_COUNT=$(find "$FRONTEND_DIST" -name "*.map" 2>/dev/null | wc -l)
[ "$MAP_COUNT" -eq 0 ]; assert_check "No source maps in frontend/dist" $?

ENV_COUNT=$(find "$FRONTEND_DIST" -name ".env*" 2>/dev/null | wc -l)
[ "$ENV_COUNT" -eq 0 ]; assert_check "No .env files in frontend/dist" $?

grep -rq "https://api\.sfumsa\.ca/api/v1" "$FRONTEND_DIST/assets" 2>/dev/null
assert_check "frontend/dist targets production API (https://api.sfumsa.ca/api/v1)" $?

if grep -rq "http://localhost:8000" "$FRONTEND_DIST/assets" 2>/dev/null; then
    assert_check "frontend/dist has NO hardcoded localhost:8000" 1 "Found localhost:8000"
else
    assert_check "frontend/dist has NO hardcoded localhost:8000" 0
fi

# --- 3. Connect Build Validation (connect/dist) ---
echo ""
echo "--- Checking connect/dist ---"
CONNECT_DIST="$ROOT/connect/dist"
[ -d "$CONNECT_DIST" ]; assert_check "connect/dist exists" $?
[ -f "$CONNECT_DIST/index.html" ]; assert_check "connect/dist/index.html exists" $?
[ -f "$CONNECT_DIST/.htaccess" ]; assert_check "connect/dist/.htaccess exists" $?
[ -f "$CONNECT_DIST/robots.txt" ]; assert_check "connect/dist/robots.txt exists" $?
[ -f "$CONNECT_DIST/sw.js" ]; assert_check "connect/dist/sw.js exists" $?
[ -f "$CONNECT_DIST/manifest.webmanifest" ]; assert_check "connect/dist/manifest.webmanifest exists" $?

C_MAP_COUNT=$(find "$CONNECT_DIST" -name "*.map" 2>/dev/null | wc -l)
[ "$C_MAP_COUNT" -eq 0 ]; assert_check "No source maps in connect/dist" $?

C_ENV_COUNT=$(find "$CONNECT_DIST" -name ".env*" 2>/dev/null | wc -l)
[ "$C_ENV_COUNT" -eq 0 ]; assert_check "No .env files in connect/dist" $?

grep -rq "https://api\.sfumsa\.ca/api/v1" "$CONNECT_DIST/assets" 2>/dev/null
assert_check "connect/dist targets production API (https://api.sfumsa.ca/api/v1)" $?

if grep -rq "http://localhost:8000" "$CONNECT_DIST/assets" 2>/dev/null; then
    assert_check "connect/dist has NO hardcoded localhost:8000" 1 "Found localhost:8000"
else
    assert_check "connect/dist has NO hardcoded localhost:8000" 0
fi

# --- 4. Backend Configuration & CORS Invariants ---
echo ""
echo "--- Checking Backend Configuration ---"
CORS_CONFIG="$ROOT/backend/config/cors.php"
grep -q "'allowed_methods'" "$CORS_CONFIG" && grep -q "'GET'" "$CORS_CONFIG"
assert_check "backend/config/cors.php sets explicit HTTP methods" $?

grep -q "'Authorization'" "$CORS_CONFIG"
assert_check "backend/config/cors.php allows Authorization header" $?

grep -q "'X-XSRF-TOKEN'" "$CORS_CONFIG"
assert_check "backend/config/cors.php allows X-XSRF-TOKEN header" $?

grep -q "'supports_credentials'\s*=>\s*true" "$CORS_CONFIG"
assert_check "backend/config/cors.php supports credentials" $?

BACKEND_HTACCESS="$ROOT/backend/public/.htaccess"
grep -q "HTTP_AUTHORIZATION" "$BACKEND_HTACCESS"
assert_check "backend/public/.htaccess handles Authorization header" $?

grep -q "HTTP_X_XSRF_TOKEN" "$BACKEND_HTACCESS"
assert_check "backend/public/.htaccess handles X-XSRF-Token header" $?

grep -q "Options.*-Indexes" "$BACKEND_HTACCESS"
assert_check "backend/public/.htaccess disables indexes" $?

echo ""
echo "=========================================================="
echo "Validation Summary: $PASSED Passed, $FAILED Failed"
echo "=========================================================="

if [ "$FAILED" -gt 0 ]; then
    exit 1
fi
exit 0
