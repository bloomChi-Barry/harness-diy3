#!/bin/bash
# 功能验证脚本 - native-http-router
# 用法: bash docs/features/native-http-router/verify.sh

set -e

PASS=0
FAIL=0
SERVER_PID=""

cleanup() {
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null
        wait "$SERVER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT

check() {
    local desc="node"
    if [ $? -eq 0 ]; then
        echo "PASS: $desc"
        PASS=$((PASS + 1))
    else
        echo "FAIL: $desc"
        FAIL=$((FAIL + 1))
    fi
}

echo "===== Native HTTP Router ====="
echo ""

# Start server
echo "[INFO] Starting server..."
node server.js &
SERVER_PID=$!

# Wait for server to be ready
echo "[INFO] Waiting for server to be ready..."
for i in $(seq 1 20); do
    if curl -s http://localhost:3000/ > /dev/null 2>&1; then
        break
    fi
    if [ $i -eq 20 ]; then
        echo "[ERROR] Server did not start within 10 seconds"
        exit 1
    fi
    sleep 0.5
done
echo ""

# Test 1: GET / returns 200
echo "--- Test 1: GET / ---"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:3000/)
BODY=$(curl -s http://localhost:3000/)
if [ "$STATUS" = "200" ] && echo "$BODY" | grep -qE "<html|<h1|<p|welcome|Welcome"; then
    echo "PASS: GET / returns 200 with HTML content"
    PASS=$((PASS + 1))
else
    echo "FAIL: GET / expected 200 with HTML, got status=$STATUS"
    FAIL=$((FAIL + 1))
fi

# Test 2: GET /api/hello returns 200 + JSON
echo "--- Test 2: GET /api/hello ---"
STATUS=$(curl -s -o /tmp/verify_hello.json -w "%{http_code}" http://localhost:3000/api/hello)
BODY=$(cat /tmp/verify_hello.json)
if [ "$STATUS" = "200" ] && echo "$BODY" | grep -q '"message"'; then
    echo "PASS: GET /api/hello returns 200 with JSON message"
    PASS=$((PASS + 1))
else
    echo "FAIL: GET /api/hello expected 200 with JSON, got status=$STATUS body=$BODY"
    FAIL=$((FAIL + 1))
fi

# Test 3: GET /api/users returns 200 + JSON array
echo "--- Test 3: GET /api/users ---"
STATUS=$(curl -s -o /tmp/verify_users.json -w "%{http_code}" http://localhost:3000/api/users)
BODY=$(cat /tmp/verify_users.json)
if [ "$STATUS" = "200" ] && echo "$BODY" | grep -qE '^\[' && echo "$BODY" | grep -q '"id"' && echo "$BODY" | grep -q '"name"' && echo "$BODY" | grep -q '"email"'; then
    echo "PASS: GET /api/users returns 200 with JSON user array"
    PASS=$((PASS + 1))
else
    echo "FAIL: GET /api/users expected 200 with JSON array, got status=$STATUS body=$BODY"
    FAIL=$((FAIL + 1))
fi

# Test 4: GET /nonexistent returns 404
echo "--- Test 4: GET /nonexistent ---"
STATUS=$(curl -s -o /tmp/verify_404.json -w "%{http_code}" http://localhost:3000/nonexistent)
if [ "$STATUS" = "404" ]; then
    echo "PASS: GET /nonexistent returns 404"
    PASS=$((PASS + 1))
else
    echo "FAIL: GET /nonexistent expected 404, got $STATUS"
    FAIL=$((FAIL + 1))
fi

# Test 5: POST /api/hello (method mismatch) returns 404
echo "--- Test 5: POST /api/hello ---"
STATUS=$(curl -s -X POST -o /dev/null -w "%{http_code}" http://localhost:3000/api/hello)
if [ "$STATUS" = "404" ]; then
    echo "PASS: POST /api/hello returns 404 (method mismatch)"
    PASS=$((PASS + 1))
else
    echo "FAIL: POST /api/hello expected 404, got $STATUS"
    FAIL=$((FAIL + 1))
fi

echo ""
echo "===== Native HTTP Router ====="
echo "Pass: $PASS, Fail: $FAIL"
[ $FAIL -eq 0 ] || exit 1
