# api-doc-404 — Fix Plan

## 1. Root Cause Summary

PHP built-in server (`php -S`) treats URIs with file extensions (e.g. `.json`) as static file requests. When `/api/doc.json` does not map to a real file under `public/`, PHP returns 404 directly, without falling back to `public/index.php`. The URI never reaches Symfony's Router, so NelmioApiDocBundle's route for `/api/doc.json` (defined in `config/routes/nelmio_api_doc.yaml`) is never matched.

The URI `/api/doc` (no extension) triggers PHP's index-file fallback, reaches Symfony, and works correctly.

## 2. Fix Strategy

Create `public/router.php` as a PHP built-in server router script. When the dev server is started with `php -S localhost:8000 -t public/ public/router.php`, the router script intercepts every request:

- If the URI maps to a real file on disk (e.g. `bundles/nelmioapidoc/*`), return `false` to let PHP serve it natively.
- Otherwise, route the request through `public/index.php` (Symfony front controller).

Update `CLAUDE.md` to reflect the new dev server command.

## 3. Files to Create

### 3.1 `public/router.php`

```php
<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $url = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $file = __DIR__ . $url;
    if (is_file($file)) {
        return false;
    }
}

$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
require_once __DIR__ . '/index.php';
```

## 4. Files to Modify

### 4.1 `CLAUDE.md` (repo root)

Line 25 currently reads: `php -S localhost:8000 -t public/`

Must be changed to: `php -S localhost:8000 -t public/ public/router.php`

## 5. Verification Plan

### Phase 1: Reproduction Confirmation (before fix)

Prove the bug exists before applying the fix.

```bash
#!/bin/bash
set -e
APP_DIR="/Users/barry/code/harness-diy3/app/demo-backend-api"
cd "$APP_DIR"
PORT=18099
echo "=== PHASE 1: Reproduction (without router.php) ==="
php -S "127.0.0.1:$PORT" -t public/ > /tmp/php-server-repro.log 2>&1 &
SERVER_PID=$!
sleep 1
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/api/doc.json")
kill "$SERVER_PID" 2>/dev/null || true
if [ "$HTTP_CODE" != "404" ]; then
    echo "REPRODUCTION FAILED: Expected 404 for /api/doc.json, got $HTTP_CODE"
    exit 1
fi
echo "PASS 1.2: /api/doc.json returns 404 (BUG CONFIRMED)"
```

### Phase 2: Fix Verification (after fix)

Prove the bug is gone and the fix works.

```bash
#!/bin/bash
set -e
APP_DIR="/Users/barry/code/harness-diy3/app/demo-backend-api"
cd "$APP_DIR"
PORT=18099
echo "=== PHASE 2: Fix Verification (with router.php) ==="
php bin/console doctrine:database:create --if-not-exists --env=dev --no-interaction
php bin/console doctrine:schema:create --env=dev --no-interaction
php -S "127.0.0.1:$PORT" -t public/ public/router.php > /tmp/php-server-fix.log 2>&1 &
SERVER_PID=$!
sleep 1
FAILURES=0
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/api/doc.json")
if [ "$HTTP_CODE" != "200" ]; then echo "FAIL: /api/doc.json expected 200, got $HTTP_CODE"; FAILURES=$((FAILURES+1)); else echo "PASS: /api/doc.json returns 200"; fi
CONTENT_TYPE=$(curl -s -o /dev/null -w "%{content_type}" "http://127.0.0.1:$PORT/api/doc.json")
if echo "$CONTENT_TYPE" | grep -q 'json'; then echo "PASS: /api/doc.json has JSON content-type"; else echo "FAIL: /api/doc.json not JSON"; FAILURES=$((FAILURES+1)); fi
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/api/doc")
if [ "$HTTP_CODE" != "200" ]; then echo "FAIL: /api/doc expected 200, got $HTTP_CODE"; FAILURES=$((FAILURES+1)); else echo "PASS: /api/doc returns 200"; fi
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/api/categories")
if [ "$HTTP_CODE" != "200" ]; then echo "FAIL: /api/categories expected 200, got $HTTP_CODE"; FAILURES=$((FAILURES+1)); else echo "PASS: /api/categories returns 200"; fi
curl -s "http://127.0.0.1:$PORT/api/categories" | python3 -c "import json,sys; json.load(sys.stdin)" 2>/dev/null && echo "PASS: /api/categories is valid JSON" || { echo "FAIL: /api/categories not valid JSON"; FAILURES=$((FAILURES+1)); }
kill "$SERVER_PID" 2>/dev/null || true
if [ "$FAILURES" -gt 0 ]; then echo "PHASE 2 FAILED with $FAILURES failure(s)"; exit 1; fi
echo "PHASE 2: ALL CHECKS PASSED"
```

### Phase 3: Syntax and Code Quality

```bash
#!/bin/bash
set -e
APP_DIR="/Users/barry/code/harness-diy3/app/demo-backend-api"
cd "$APP_DIR"
echo "=== PHASE 3: Syntax and Code Quality ==="
php -l public/router.php
echo "PASS: router.php syntax OK"
vendor/bin/php-cs-fixer fix public/router.php --dry-run --diff --allow-unsupported-php-version true
echo "PASS: router.php passes CS fixer"
vendor/bin/phpstan analyze --no-progress
echo "PASS: PHPStan analysis clean"
php bin/phpunit
echo "PASS: All PHPUnit tests pass"
echo "PHASE 3: ALL CHECKS PASSED"
```
