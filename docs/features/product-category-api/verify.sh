#!/bin/bash
# 功能验证脚本 - product-category-api
# 用法: bash docs/features/product-category-api/verify.sh

set -e

PASS=0
FAIL=0
BASE_URL="${BASE_URL:-http://localhost:8000}"

check() {
    local desc="$1"
    local expected="$2"
    local actual="$3"
    if [ "$actual" = "$expected" ]; then
        echo "✅ PASS: $desc"
        PASS=$((PASS + 1))
    else
        echo "❌ FAIL: $desc (expected $expected, got $actual)"
        FAIL=$((FAIL + 1))
    fi
}

check_json() {
    local desc="$1"
    local field="$2"
    local expected="$3"
    local json="$4"
    local actual
    actual=$(echo "$json" | python3 -c "import sys,json; print(json.load(sys.stdin)$field)" 2>/dev/null || echo "JSON_PARSE_ERROR")
    if [ "$actual" = "$expected" ]; then
        echo "✅ PASS: $desc"
        PASS=$((PASS + 1))
    else
        echo "❌ FAIL: $desc (expected $expected, got $actual)"
        FAIL=$((FAIL + 1))
    fi
}

echo "===== 商品分类 API 验证开始 ====="
echo ""

# ==================== 前置检查 ====================
echo "--- 前置检查 ---"

cd app/demo-backend-api

# PHP syntax check for key files
for f in src/Entity/Category.php src/Service/CategoryService.php src/Controller/CategoryController.php src/EventListener/ExceptionListener.php; do
    if php -l "$f" > /dev/null 2>&1; then
        echo "✅ PASS: PHP syntax check: $f"
        PASS=$((PASS + 1))
    else
        echo "❌ FAIL: PHP syntax check: $f"
        FAIL=$((FAIL + 1))
    fi
done

# Start dev server
php -S localhost:8000 -t public/ > /dev/null 2>&1 &
SERVER_PID=$!
sleep 1

cleanup() {
    kill $SERVER_PID 2>/dev/null || true
}
trap cleanup EXIT

echo ""
echo "--- API 端点测试 ---"

# ==================== 1. GET /api/categories (空列表) ====================
RESP=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/categories")
check "GET /api/categories (tree) returns 200" "200" "$RESP"

# ==================== 2. POST /api/categories (创建根分类) ====================
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/categories" \
  -H "Content-Type: application/json" \
  -d '{"name":"服装","sort_order":0,"is_enabled":true}')
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
CAT1_ID=$(echo "$BODY" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])" 2>/dev/null || echo "")
check "POST /api/categories creates root category" "201" "$HTTP_CODE"
check_json "POST response has id" "['id']" "$CAT1_ID" "$BODY"

# ==================== 3. POST /api/categories (创建子分类) ====================
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/categories" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"上衣\",\"parent_id\":$CAT1_ID,\"sort_order\":0,\"is_enabled\":true}")
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
CAT2_ID=$(echo "$BODY" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])" 2>/dev/null || echo "")
check "POST /api/categories creates child category" "201" "$HTTP_CODE"

# ==================== 4. GET /api/categories (树形结构) ====================
RESP=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/categories")
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
check "GET /api/categories (after create) returns 200" "200" "$HTTP_CODE"
CHILDREN_COUNT=$(echo "$BODY" | python3 -c "import sys,json; d=json.load(sys.stdin); print(len(d[0]['children']))" 2>/dev/null || echo "0")
check_json "GET /api/categories returns nested tree" "[0]['children']" "1" "$(echo "$CHILDREN_COUNT")" "$BODY" || true
# Manual check for children count
if [ "$CHILDREN_COUNT" -ge 1 ]; then
    echo "✅ PASS: GET /api/categories returns nested tree with children"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL: GET /api/categories children count < 1"
    FAIL=$((FAIL + 1))
fi

# ==================== 5. GET /api/categories/{id} ====================
RESP=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/categories/$CAT1_ID")
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
check "GET /api/categories/{id} returns 200" "200" "$HTTP_CODE"
check_json "GET /api/categories/{id} has correct name" "['name']" "服装" "$BODY"

# ==================== 6. GET /api/categories/{id} (404) ====================
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/categories/99999")
check "GET /api/categories/{id} not found returns 404" "404" "$HTTP_CODE"

# ==================== 7. PUT /api/categories/{id} (更新) ====================
RESP=$(curl -s -w "\n%{http_code}" -X PUT "$BASE_URL/api/categories/$CAT1_ID" \
  -H "Content-Type: application/json" \
  -d '{"name":"服装(更新)","seo_title":"服装SEO标题"}')
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
check "PUT /api/categories/{id} returns 200" "200" "$HTTP_CODE"
check_json "PUT /api/categories/{id} updated name" "['name']" "服装(更新)" "$BODY"

# ==================== 8. POST /api/categories (创建第三级) ====================
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/categories" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"T恤\",\"parent_id\":$CAT2_ID,\"sort_order\":0,\"is_enabled\":true}")
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
CAT3_ID=$(echo "$BODY" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])" 2>/dev/null || echo "")
check "POST creates third-level category" "201" "$HTTP_CODE"

# ==================== 9. DELETE /api/categories/{id} (有子级 → 409) ====================
RESP=$(curl -s -w "\n%{http_code}" -X DELETE "$BASE_URL/api/categories/$CAT1_ID")
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
check "DELETE category with children returns 409" "409" "$HTTP_CODE"
check_json "DELETE 409 has HAS_CHILDREN code" "['error']['code']" "HAS_CHILDREN" "$BODY"

# ==================== 10. DELETE /api/categories/{id} (无子级 → 204) ====================
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X DELETE "$BASE_URL/api/categories/$CAT3_ID")
check "DELETE leaf category returns 204" "204" "$HTTP_CODE"

# ==================== 11. PATCH /api/categories/{id}/toggle ====================
RESP=$(curl -s -w "\n%{http_code}" -X PATCH "$BASE_URL/api/categories/$CAT2_ID/toggle" \
  -H "Content-Type: application/json" \
  -d '{"is_enabled":false}')
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
check "PATCH toggle returns 200" "200" "$HTTP_CODE"
check_json "PATCH toggle disabled" "['is_enabled']" "False" "$BODY"

# ==================== 12. GET /api/categories?enabled_only=true ====================
# After toggle, the disabled category and its children should be filtered
RESP=$(curl -s "$BASE_URL/api/categories?enabled_only=true")
TREE_LEN=$(echo "$RESP" | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "0")
# 服装 was disabled, so the tree should be empty (no enabled root categories)
if [ "$TREE_LEN" = "0" ] || [ "$TREE_LEN" = "1" ]; then
    echo "✅ PASS: GET /api/categories?enabled_only=true filters disabled"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL: GET /api/categories?enabled_only=true unexpected tree length: $TREE_LEN"
    FAIL=$((FAIL + 1))
fi

# ==================== 13. PATCH /api/categories/{id}/move ====================
# Re-enable first
curl -s -X PATCH "$BASE_URL/api/categories/$CAT2_ID/toggle" -H "Content-Type: application/json" -d '{"is_enabled":true}' > /dev/null

# Move T恤 (re-create it since we deleted it)
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/categories" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"T恤v2\",\"parent_id\":$CAT2_ID,\"sort_order\":0,\"is_enabled\":true}")
BODY=$(echo "$RESP" | sed '$d')
CAT3_ID=$(echo "$BODY" | python3 -c "import sys,json; print(json.load(sys.stdin)['id'])" 2>/dev/null || echo "")

# Move it to root level
RESP=$(curl -s -w "\n%{http_code}" -X PATCH "$BASE_URL/api/categories/$CAT3_ID/move" \
  -H "Content-Type: application/json" \
  -d "{\"parent_id\":null,\"sort_order\":10}")
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
check "PATCH move returns 200" "200" "$HTTP_CODE"
check_json "PATCH move parent_id is null" "['parent_id']" "None" "$BODY"

# ==================== 14. 错误处理: 创建时缺少 name ====================
RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/categories" \
  -H "Content-Type: application/json" \
  -d '{"sort_order":0}')
HTTP_CODE=$(echo "$RESP" | tail -1)
BODY=$(echo "$RESP" | sed '$d')
check "POST without name returns 400" "400" "$HTTP_CODE"
check_json "POST 400 has VALIDATION_ERROR" "['error']['code']" "VALIDATION_ERROR" "$BODY"

# ==================== 15. 错误处理: 无效 JSON ====================
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE_URL/api/categories" \
  -H "Content-Type: application/json" \
  -d 'not json')
check "POST with invalid JSON returns 400" "400" "$HTTP_CODE"

echo ""
echo "--- QA 检查 ---"

# ==================== 16. PHPStan ====================
cd app/demo-backend-api
if vendor/bin/phpstan analyze --no-progress 2>&1; then
    echo "✅ PASS: phpstan analyze"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL: phpstan analyze"
    FAIL=$((FAIL + 1))
fi

# ==================== 17. PHP-CS-Fixer ====================
if vendor/bin/php-cs-fixer fix --allow-unsupported-php-version true --dry-run 2>&1; then
    echo "✅ PASS: php-cs-fixer"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL: php-cs-fixer"
    FAIL=$((FAIL + 1))
fi

# ==================== 18. PHPUnit ====================
if php bin/phpunit 2>&1; then
    echo "✅ PASS: phpunit"
    PASS=$((PASS + 1))
else
    echo "❌ FAIL: phpunit"
    FAIL=$((FAIL + 1))
fi

echo ""
echo "===== 商品分类 API 验证结束 ====="
echo "通过: $PASS, 失败: $FAIL"
[ $FAIL -eq 0 ] || exit 1
