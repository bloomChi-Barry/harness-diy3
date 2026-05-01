#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# ---- load env ----
set -a
[ -f "$PROJECT_DIR/.env" ]       && source "$PROJECT_DIR/.env"
[ -f "$PROJECT_DIR/.env.dev" ]   && source "$PROJECT_DIR/.env.dev"
[ -f "$PROJECT_DIR/.env.local" ] && source "$PROJECT_DIR/.env.local"
set +a

# ---- config ----
APIFOX_BASE_URL="${APIFOX_BASE_URL:-https://api.apifox.cn}"
SERVER_URL="${1:-http://localhost:8000}"
DOC_JSON_URL="$SERVER_URL/api/doc.json"

APIFOX_API_TOKEN="${APIFOX_API_TOKEN:-}"
APIFOX_PROJECT_ID="${APIFOX_PROJECT_ID:-}"
OVERWRITE="${APIFOX_ENDPOINT_OVERWRITE:-AUTO_MERGE}"

# ---- validate ----
if [ -z "$APIFOX_API_TOKEN" ] || [ -z "$APIFOX_PROJECT_ID" ]; then
    echo "❌ Missing required environment variables."
    echo "   Set APIFOX_API_TOKEN and APIFOX_PROJECT_ID in .env.local or environment."
    echo ""
    echo "   APIFOX_API_TOKEN  — Bearer token from Apifox → Project Settings → Open API"
    echo "   APIFOX_PROJECT_ID — Numeric project ID (visible in project URL)"
    exit 1
fi

if ! command -v curl &>/dev/null; then
    echo "❌ curl is required but not installed."
    exit 1
fi

# ---- helpers ----
cleanup() {
    if [ -n "${DEV_SERVER_PID:-}" ]; then
        kill "$DEV_SERVER_PID" 2>/dev/null || true
        wait "$DEV_SERVER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT

# ---- ensure dev server is running ----
DEV_SERVER_PID=""
if ! curl -s -o /dev/null -w '%{http_code}' "$SERVER_URL" 2>/dev/null | grep -q '^[23]'; then
    echo "⚡ Starting dev server on $SERVER_URL ..."
    php -S localhost:8000 -t "$PROJECT_DIR/public/" "$PROJECT_DIR/public/router.php" &>/dev/null &
    DEV_SERVER_PID=$!

    # wait up to 5s for the server to be ready
    for _ in $(seq 1 25); do
        if curl -s -o /dev/null -w '%{http_code}' "$SERVER_URL" 2>/dev/null | grep -q '^[23]'; then
            break
        fi
        sleep 0.2
    done

    if ! curl -s -o /dev/null "$SERVER_URL" 2>/dev/null; then
        echo "❌ Failed to start dev server."
        exit 1
    fi
else
    echo "♻️  Using existing server at $SERVER_URL"
fi

# ---- fetch OpenAPI spec ----
echo "📥 Fetching OpenAPI spec from $DOC_JSON_URL ..."
OPENAPI_JSON=$(curl -sS "$DOC_JSON_URL")
if [ -z "$OPENAPI_JSON" ]; then
    echo "❌ Empty response from $DOC_JSON_URL"
    exit 1
fi

# validate JSON
if ! echo "$OPENAPI_JSON" | python3 -m json.tool >/dev/null 2>&1; then
    echo "❌ Response is not valid JSON"
    exit 1
fi

ENDPOINT_COUNT=$(echo "$OPENAPI_JSON" | python3 -c "import json,sys; p=json.load(sys.stdin).get('paths',{}); print(len(p))")
echo "   Found $ENDPOINT_COUNT path(s) to sync."

# ---- push to ApiFox ----
API_URL="$APIFOX_BASE_URL/v1/projects/$APIFOX_PROJECT_ID/import-openapi"

echo "📤 Pushing to ApiFox (project $APIFOX_PROJECT_ID, behavior: $OVERWRITE) ..."

HTTP_CODE=$(curl -sS -w '%{http_code}' -o /tmp/apifox-response.json \
    -X POST "$API_URL" \
    -H "Authorization: Bearer $APIFOX_API_TOKEN" \
    -H "X-Apifox-Api-Version: 2024-03-28" \
    -H "Content-Type: application/json" \
    -d "$(python3 -c "
import json, sys
spec = json.loads(sys.stdin.read())
payload = {
    'input': json.dumps(spec),
    'options': {
        'endpointOverwriteBehavior': '$OVERWRITE'
    }
}
print(json.dumps(payload))
" <<< "$OPENAPI_JSON")")

if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    echo "✅ Sync successful (HTTP $HTTP_CODE)"
    python3 -m json.tool /tmp/apifox-response.json 2>/dev/null || cat /tmp/apifox-response.json
else
    echo "❌ Sync failed (HTTP $HTTP_CODE)"
    python3 -m json.tool /tmp/apifox-response.json 2>/dev/null || cat /tmp/apifox-response.json
    exit 1
fi
