#!/usr/bin/env bash
set -euo pipefail

# ── Config ────────────────────────────────────────────────────────
PORT=8901
BASE="http://localhost:${PORT}"
API="${BASE}/api/categories"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/../../../app/demo-backend-api" && pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

PASSED=0
FAILED=0

assert_status() {
    local desc="$1" expected="$2" actual="$3"
    if [ "$expected" = "$actual" ]; then
        echo -e "  ${GREEN}✓${NC} ${desc}"
        PASSED=$((PASSED + 1))
    else
        echo -e "  ${RED}✗${NC} ${desc} (expected ${expected}, got ${actual})"
        FAILED=$((FAILED + 1))
    fi
}

assert_json_field() {
    local desc="$1" expected="$2" actual="$3"
    if [ "$expected" = "$actual" ]; then
        echo -e "  ${GREEN}✓${NC} ${desc}"
        PASSED=$((PASSED + 1))
    else
        echo -e "  ${RED}✗${NC} ${desc} (expected '${expected}', got '${actual}')"
        FAILED=$((FAILED + 1))
    fi
}

# ── Database setup ────────────────────────────────────────────────
echo "=== Setting up database ==="
cd "${APP_DIR}"
rm -f var/data.db
HTTP_PROXY=http://127.0.0.1:6244 HTTPS_PROXY=http://127.0.0.1:6244 php bin/console doctrine:schema:create --no-interaction 2>&1

# Seed initial data via SQLite
php <<'SEEDPHP' 2>&1
<?php
$db = new \PDO('sqlite:var/data.db');
$db->exec('DELETE FROM category');
$db->exec("INSERT INTO category (id, name, sort_order, is_enabled, created_at, updated_at, parent_id) VALUES (1, 'Electronics', 0, 1, datetime('now'), datetime('now'), NULL)");
$db->exec("INSERT INTO category (id, name, sort_order, is_enabled, created_at, updated_at, parent_id) VALUES (2, 'Phones', 1, 1, datetime('now'), datetime('now'), 1)");
$db->exec("INSERT INTO category (id, name, sort_order, is_enabled, created_at, updated_at, parent_id) VALUES (3, 'iPhone', 0, 1, datetime('now'), datetime('now'), 2)");
$db->exec("INSERT INTO category (id, name, sort_order, is_enabled, created_at, updated_at, parent_id) VALUES (4, 'Laptops', 2, 0, datetime('now'), datetime('now'), 1)");
$db->exec("INSERT INTO category (id, name, sort_order, is_enabled, created_at, updated_at, parent_id) VALUES (5, 'Books', 3, 1, datetime('now'), datetime('now'), NULL)");
echo "Seed data inserted\n";
SEEDPHP

# ── Start server ──────────────────────────────────────────────────
echo "=== Starting PHP server on port ${PORT} ==="
php -S "localhost:${PORT}" -t public/ public/index.php > /tmp/verify-server.log 2>&1 &
SERVER_PID=$!
sleep 1

cleanup() {
    echo ""
    echo "=== Stopping server (PID ${SERVER_PID}) ==="
    kill "${SERVER_PID}" 2>/dev/null || true
    wait "${SERVER_PID}" 2>/dev/null || true
    echo "=== Cleaning up database ==="
    rm -f "${APP_DIR}/var/data.db"
}
trap cleanup EXIT

# ── 1. GET /api/categories ────────────────────────────────────────
echo ""
echo "--- 1. GET /api/categories (tree) ---"
RESP=$(curl -s -w "\n%{http_code}" "${API}")
STATUS=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
assert_status "GET /api/categories → 200" 200 "${STATUS}"
ROOT_COUNT=$(echo "${BODY}" | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "0")
assert_json_field "Has at least 1 root category" "1" "$( [ "${ROOT_COUNT}" -gt 0 ] && echo 1 || echo 0 )"

# ── 2. GET /api/categories?enabled_only=true ──────────────────────
echo ""
echo "--- 2. GET /api/categories?enabled_only=true (filter) ---"
RESP=$(curl -s -w "\n%{http_code}" "${API}?enabled_only=true")
STATUS=$(echo "$RESP" | tail -1)
assert_status "GET /api/categories?enabled_only=true → 200" 200 "${STATUS}"

# ── 3. GET /api/categories/1 ──────────────────────────────────────
echo ""
echo "--- 3. GET /api/categories/1 (detail) ---"
RESP=$(curl -s -w "\n%{http_code}" "${API}/1")
STATUS=$(echo "$RESP" | tail -1)
assert_status "GET /api/categories/1 → 200" 200 "${STATUS}"

# ── 4. GET /api/categories/9999 (404) ─────────────────────────────
echo ""
echo "--- 4. GET /api/categories/9999 (not found) ---"
RESP=$(curl -s -w "\n%{http_code}" "${API}/9999")
STATUS=$(echo "$RESP" | tail -1)
assert_status "GET /api/categories/9999 → 404" 404 "${STATUS}"

# ── 5. POST /api/categories (create) ──────────────────────────────
echo ""
echo "--- 5. POST /api/categories (create) ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "${API}" \
    -H 'Content-Type: application/json' \
    -d '{"name":"Verify Test","sort_order":99}')
STATUS=$(echo "$RESP" | tail -1)
assert_status "POST /api/categories → 201" 201 "${STATUS}"
BODY=$(echo "$RESP" | sed '$d')
NEW_ID=$(echo "${BODY}" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])" 2>/dev/null || echo "")

# ── 6. POST /api/categories (missing name → 400) ──────────────────
echo ""
echo "--- 6. POST /api/categories (validation error) ---"
RESP=$(curl -s -w "\n%{http_code}" -X POST "${API}" \
    -H 'Content-Type: application/json' \
    -d '{"sort_order":1}')
STATUS=$(echo "$RESP" | tail -1)
assert_status "POST /api/categories (no name) → 400" 400 "${STATUS}"

# ── 7. PUT /api/categories/{id} (update) ──────────────────────────
echo ""
echo "--- 7. PUT /api/categories/{id} (update) ---"
UPDATE_ID="${NEW_ID:-1}"
RESP=$(curl -s -w "\n%{http_code}" -X PUT "${API}/${UPDATE_ID}" \
    -H 'Content-Type: application/json' \
    -d '{"name":"Updated Name"}')
STATUS=$(echo "$RESP" | tail -1)
assert_status "PUT /api/categories/${UPDATE_ID} → 200" 200 "${STATUS}"

# ── 8. PUT /api/categories/9999 (not found → 404) ─────────────────
echo ""
echo "--- 8. PUT /api/categories/9999 (not found) ---"
RESP=$(curl -s -w "\n%{http_code}" -X PUT "${API}/9999" \
    -H 'Content-Type: application/json' \
    -d '{"name":"Nope"}')
STATUS=$(echo "$RESP" | tail -1)
assert_status "PUT /api/categories/9999 → 404" 404 "${STATUS}"

# ── 9. PATCH /api/categories/{id}/toggle ──────────────────────────
echo ""
echo "--- 9. PATCH /api/categories/{id}/toggle ---"
RESP=$(curl -s -w "\n%{http_code}" -X PATCH "${API}/1/toggle" \
    -H 'Content-Type: application/json' \
    -d '{"is_enabled":false}')
STATUS=$(echo "$RESP" | tail -1)
assert_status "PATCH /api/categories/1/toggle → 200" 200 "${STATUS}"

# ── 10. PATCH /api/categories/{id}/move ───────────────────────────
echo ""
echo "--- 10. PATCH /api/categories/{id}/move ---"
RESP=$(curl -s -w "\n%{http_code}" -X PATCH "${API}/2/move" \
    -H 'Content-Type: application/json' \
    -d '{"sort_order":55}')
STATUS=$(echo "$RESP" | tail -1)
assert_status "PATCH /api/categories/2/move → 200" 200 "${STATUS}"

# ── 11. DELETE /api/categories/{id} ───────────────────────────────
echo ""
echo "--- 11. DELETE /api/categories/{id} ---"
if [ -n "${NEW_ID:-}" ] && [ "${NEW_ID}" != "1" ]; then
    RESP=$(curl -s -w "\n%{http_code}" -X DELETE "${API}/${NEW_ID}")
    STATUS=$(echo "$RESP" | tail -1)
    assert_status "DELETE /api/categories/${NEW_ID} → 204" 204 "${STATUS}"
else
    RESP=$(curl -s -w "\n%{http_code}" -X POST "${API}" \
        -H 'Content-Type: application/json' \
        -d '{"name":"To Delete","sort_order":100}')
    BODY=$(echo "$RESP" | sed '$d')
    DEL_ID=$(echo "${BODY}" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])" 2>/dev/null || echo "")
    RESP=$(curl -s -w "\n%{http_code}" -X DELETE "${API}/${DEL_ID}")
    STATUS=$(echo "$RESP" | tail -1)
    assert_status "DELETE /api/categories/${DEL_ID} → 204" 204 "${STATUS}"
fi

# ── 12. DELETE /api/categories/1 (has children → 409) ─────────────
echo ""
echo "--- 12. DELETE /api/categories/1 (has children → 409) ---"
RESP=$(curl -s -w "\n%{http_code}" -X DELETE "${API}/1")
STATUS=$(echo "$RESP" | tail -1)
assert_status "DELETE /api/categories/1 → 409" 409 "${STATUS}"

# ── QA Checks ─────────────────────────────────────────────────────
echo ""
echo "========================================="
echo "  QA Checks"
echo "========================================="

cd "${APP_DIR}"

echo ""
echo "--- PHP-CS-Fixer (dry-run) ---"
if HTTP_PROXY=http://127.0.0.1:6244 HTTPS_PROXY=http://127.0.0.1:6244 vendor/bin/php-cs-fixer fix --allow-unsupported-php-version true --dry-run 2>&1; then
    echo -e "${GREEN}✓${NC} PHP-CS-Fixer passed"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} PHP-CS-Fixer failed"
    FAILED=$((FAILED + 1))
fi

echo ""
echo "--- PHPStan (level 6) ---"
if HTTP_PROXY=http://127.0.0.1:6244 HTTPS_PROXY=http://127.0.0.1:6244 vendor/bin/phpstan analyze 2>&1; then
    echo -e "${GREEN}✓${NC} PHPStan passed"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} PHPStan failed"
    FAILED=$((FAILED + 1))
fi

echo ""
echo "--- PHPUnit ---"
if HTTP_PROXY=http://127.0.0.1:6244 HTTPS_PROXY=http://127.0.0.1:6244 php bin/phpunit 2>&1; then
    echo -e "${GREEN}✓${NC} PHPUnit passed"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} PHPUnit failed"
    FAILED=$((FAILED + 1))
fi

echo ""
echo "--- Cache Clear ---"
if HTTP_PROXY=http://127.0.0.1:6244 HTTPS_PROXY=http://127.0.0.1:6244 php bin/console cache:clear 2>&1; then
    echo -e "${GREEN}✓${NC} Cache clear passed"
    PASSED=$((PASSED + 1))
else
    echo -e "${RED}✗${NC} Cache clear failed"
    FAILED=$((FAILED + 1))
fi

# ── Summary ───────────────────────────────────────────────────────
echo ""
echo "========================================="
echo "  Results: ${PASSED} passed, ${FAILED} failed"
echo "========================================="

if [ "${FAILED}" -gt 0 ]; then
    exit 1
fi
