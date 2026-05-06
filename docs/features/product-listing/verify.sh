#!/usr/bin/env bash
set -o pipefail

PASS=0
FAIL=0
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

run_and_check() {
    local desc="$1"
    shift
    printf "  %s ... " "$desc"
    if "$@" > /dev/null 2>&1; then
        printf "${GREEN}PASS${NC}\n"
        PASS=$((PASS + 1))
        return 0
    else
        printf "${RED}FAIL${NC}\n"
        FAIL=$((FAIL + 1))
        return 1
    fi
}

check_eq() {
    local desc="$1" expected="$2" actual="$3"
    printf "  %s ... " "$desc"
    if [ "$expected" = "$actual" ]; then
        printf "${GREEN}PASS${NC}\n"
        PASS=$((PASS + 1))
        return 0
    else
        printf "${RED}FAIL${NC} (expected='%s' actual='%s')\n" "$expected" "$actual"
        FAIL=$((FAIL + 1))
        return 1
    fi
}

echo "=== Product Listing Feature - Verification ==="
echo ""

PROJECT_DIR="/Users/barry/code/harness-diy3/app/demo-backend-api"

# ── TASK 01: Entity ────────────────────────────
echo "--- Task 01: Product Entity ---"
run_and_check "PHP syntax check for Product.php" \
    php -l "$PROJECT_DIR/src/Entity/Product.php"
run_and_check "Product class exists" \
    php -r "require '$PROJECT_DIR/vendor/autoload.php'; exit(class_exists('App\Entity\Product') ? 0 : 1);"

# ── TASK 02: DTO ───────────────────────────────
echo ""
echo "--- Task 02: ProductOutput DTO ---"
run_and_check "PHP syntax check for ProductOutput.php" \
    php -l "$PROJECT_DIR/src/Dto/ProductOutput.php"
run_and_check "ProductOutput class exists" \
    php -r "require '$PROJECT_DIR/vendor/autoload.php'; exit(class_exists('App\Dto\ProductOutput') ? 0 : 1);"

# ── TASK 03: Repository ────────────────────────
echo ""
echo "--- Task 03: ProductRepository ---"
run_and_check "PHP syntax check for ProductRepositoryInterface.php" \
    php -l "$PROJECT_DIR/src/Repository/ProductRepositoryInterface.php"
run_and_check "PHP syntax check for ProductRepository.php" \
    php -l "$PROJECT_DIR/src/Repository/ProductRepository.php"
run_and_check "Repository interface exists" \
    php -r "require '$PROJECT_DIR/vendor/autoload.php'; exit(interface_exists('App\Repository\ProductRepositoryInterface') ? 0 : 1);"
run_and_check "Repository implementation exists" \
    php -r "require '$PROJECT_DIR/vendor/autoload.php'; exit(class_exists('App\Repository\ProductRepository') ? 0 : 1);"

# ── TASK 04: Service ───────────────────────────
echo ""
echo "--- Task 04: ProductService ---"
run_and_check "PHP syntax check for ProductService.php" \
    php -l "$PROJECT_DIR/src/Service/ProductService.php"
run_and_check "ProductService class exists" \
    php -r "require '$PROJECT_DIR/vendor/autoload.php'; exit(class_exists('App\Service\ProductService') ? 0 : 1);"

# ── TASK 05: Controller + Route ────────────────
echo ""
echo "--- Task 05: ProductController ---"
run_and_check "PHP syntax check for ProductController.php" \
    php -l "$PROJECT_DIR/src/Controller/ProductController.php"
run_and_check "Route /api/products registered" \
    php "$PROJECT_DIR/bin/console" debug:router --no-ansi | grep -qE "app_product_(list|index)" || false

# ── TASK 06: Service Unit Tests ────────────────
echo ""
echo "--- Task 06: ProductServiceTest ---"
run_and_check "PHPUnit ProductServiceTest" \
    php "$PROJECT_DIR/bin/phpunit" --no-ansi --filter=ProductServiceTest

# ── TASK 07: Controller Integration Tests ──────
echo ""
echo "--- Task 07: ProductControllerTest ---"
run_and_check "PHPUnit ProductControllerTest" \
    php "$PROJECT_DIR/bin/phpunit" --no-ansi --filter=ProductControllerTest

# ── TASK 08: Code Quality ──────────────────────
echo ""
echo "--- Task 08: Code Quality ---"
run_and_check "PHP-CS-Fixer dry-run" \
    php "$PROJECT_DIR/vendor/bin/php-cs-fixer" fix --dry-run --allow-unsupported-php-version true --no-ansi
run_and_check "PHPStan analyze" \
    php "$PROJECT_DIR/vendor/bin/phpstan" analyze --no-ansi --no-interaction
run_and_check "PHPUnit full suite" \
    php "$PROJECT_DIR/bin/phpunit" --no-ansi

# ── Summary ────────────────────────────────────
echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
if [ "$FAIL" -gt 0 ]; then
    exit 1
fi
exit 0
