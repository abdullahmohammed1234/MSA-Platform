#!/usr/bin/env bash
# SFU MSA Digital Platform — Post-Deployment Smoke Test (Bash / curl)
# Run by the operator after cPanel release to verify all three endpoints live.

set -Eeuo pipefail

MAIN_HOST="${1:-https://sfumsa.ca}"
API_HOST="${2:-https://api.sfumsa.ca}"
CONNECT_HOST="${3:-https://connect.sfumsa.ca}"
PROJECTS_HOST="${4:-https://projects.sfumsa.ca}"

echo "=========================================================="
echo "SFU MSA Platform — Post-Deployment Live Smoke Tests"
echo "Main:     $MAIN_HOST"
echo "API:      $API_HOST"
echo "Connect:  $CONNECT_HOST"
echo "Projects: $PROJECTS_HOST"
echo "=========================================================="

PASSED=0
FAILED=0

test_endpoint() {
    local name="$1"
    local url="$2"
    local expected_status="$3"
    local expected_content="${4:-}"
    local origin="${5:-}"

    local curl_cmd=(curl -s -S -o /tmp/smoke_out.txt -w "%{http_code}" --max-time 15 "$url")
    if [ -n "$origin" ]; then
        curl_cmd+=(-H "Origin: $origin" -H "Access-Control-Request-Method: GET")
    fi

    local status
    status=$("${curl_cmd[@]}" 2>/dev/null || echo "000")

    local status_ok=0
    if [ "$status" -eq "$expected_status" ]; then
        status_ok=1
    fi

    local content_ok=1
    if [ -n "$expected_content" ] && [ -f /tmp/smoke_out.txt ]; then
        if ! grep -q "$expected_content" /tmp/smoke_out.txt; then
            content_ok=0
        fi
    fi

    if [ "$status_ok" -eq 1 ] && [ "$content_ok" -eq 1 ]; then
        echo " [PASS] $name (HTTP $status)"
        PASSED=$((PASSED + 1))
    else
        echo " [FAIL] $name (HTTP $status, expected $expected_status)"
        FAILED=$((FAILED + 1))
    fi
}

echo ""
echo "--- 1. Main Website Checks ---"
test_endpoint "Main Homepage" "$MAIN_HOST/" 200 'id="app"'
test_endpoint "Main robots.txt" "$MAIN_HOST/robots.txt" 200 "Sitemap:"
test_endpoint "Main sitemap.xml" "$MAIN_HOST/sitemap.xml" 200 "urlset"

echo ""
echo "--- 2. API Health & CORS Checks ---"
test_endpoint "API Health Probe (/up)" "$API_HOST/up" 200
test_endpoint "API Public Events Endpoint" "$API_HOST/api/v1/ems/public/events" 200

echo ""
echo "--- 2b. Projects Origin CORS Preflight ---"
test_projects_preflight() {
    local name="$1"
    local method="$2"
    local path="$3"
    local url="$API_HOST$path"
    local headers
    headers=$(curl -s -S -D - -o /dev/null --max-time 15 -X OPTIONS "$url" \
        -H "Origin: $PROJECTS_HOST" \
        -H "Access-Control-Request-Method: $method" \
        -H "Access-Control-Request-Headers: authorization,content-type,x-requested-with" 2>/dev/null || true)
    local status
    status=$(printf '%s' "$headers" | head -n 1 | grep -oE '[0-9]{3}' | head -n 1 || echo "000")
    if printf '%s' "$headers" | grep -qi "access-control-allow-origin: $PROJECTS_HOST" \
        && { [ "$status" = "200" ] || [ "$status" = "204" ]; }; then
        echo " [PASS] $name (HTTP $status + ACAO)"
        PASSED=$((PASSED + 1))
    else
        echo " [FAIL] $name (HTTP $status; missing Projects ACAO)"
        FAILED=$((FAILED + 1))
    fi
}
test_projects_preflight "Projects CORS OPTIONS /api/v1/pm/departments" "GET" "/api/v1/pm/departments"
test_projects_preflight "Projects CORS OPTIONS /api/v1/auth/login" "POST" "/api/v1/auth/login"

echo ""
echo "--- 3. Connect Frontend Checks ---"
test_endpoint "Connect Homepage" "$CONNECT_HOST/" 200 'id="app"'
test_endpoint "Connect Deep Route (SPA History Mode)" "$CONNECT_HOST/login" 200 'id="app"'
test_endpoint "Connect robots.txt" "$CONNECT_HOST/robots.txt" 200 "Disallow: /app/"
test_endpoint "Connect Service Worker" "$CONNECT_HOST/sw.js" 200

echo ""
echo "--- 4. Projects Frontend Checks ---"
test_endpoint "Projects Homepage" "$PROJECTS_HOST/" 200 'id="app"'
test_endpoint "Projects Deep Route (SPA History Mode)" "$PROJECTS_HOST/login" 200 'id="app"'
test_endpoint "Projects Manifest" "$PROJECTS_HOST/manifest.webmanifest" 200 "MSA PMS"
test_endpoint "Projects Service Worker" "$PROJECTS_HOST/sw.js" 200
test_endpoint "Projects Offline Page" "$PROJECTS_HOST/offline.html" 200 "offline"

echo ""
echo "=========================================================="
echo "Smoke Test Results: $PASSED Passed, $FAILED Failed"
echo "=========================================================="

if [ "$FAILED" -gt 0 ]; then
    exit 1
fi
exit 0
