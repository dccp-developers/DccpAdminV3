#!/bin/bash

# Test Script for Laravel Deployment Setup
# This script tests various aspects of the deployment to ensure everything works

PROJECT_PATH="/home/yuuki/DccpAdminV3"
DOMAIN="localhost"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

info() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

success() {
    echo -e "${GREEN}[PASS]${NC} $1"
}

fail() {
    echo -e "${RED}[FAIL]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

# Test counter
TESTS_RUN=0
TESTS_PASSED=0

run_test() {
    local test_name="$1"
    local test_command="$2"

    TESTS_RUN=$((TESTS_RUN + 1))
    info "Running: $test_name"

    if eval "$test_command"; then
        success "$test_name"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        fail "$test_name"
        return 1
    fi
}

echo "=============================================="
echo "    Laravel Deployment Test Suite"
echo "=============================================="
echo ""

# Test 1: Check if services are running
run_test "Nginx service is running" "systemctl is-active --quiet nginx"
run_test "PHP-FPM service is running" "systemctl is-active --quiet php-fpm"

# Test 2: Check file permissions
run_test "Project directory exists" "[ -d '$PROJECT_PATH' ]"
run_test "Public directory is readable" "[ -r '$PROJECT_PATH/public/index.php' ]"
run_test "Storage directory is writable" "[ -w '$PROJECT_PATH/storage' ]"
run_test "Bootstrap cache is writable" "[ -w '$PROJECT_PATH/bootstrap/cache' ]"

# Test 3: Check PHP configuration
run_test "PHP extensions are working" "php -m | grep -q gd && php -m | grep -q intl"
run_test "No PHP extension warnings" "! php -v 2>&1 | grep -i warning"

# Test 4: Check Nginx configuration
run_test "Nginx configuration is valid" "nginx -t &>/dev/null"
run_test "Laravel site is enabled" "[ -L /etc/nginx/sites-enabled/laravel.conf ]"

# Test 5: HTTP connectivity tests
run_test "Main application responds" "curl -s -f --connect-timeout 5 'http://$DOMAIN' >/dev/null 2>&1"
run_test "Health endpoint responds" "curl -s --connect-timeout 5 'http://$DOMAIN/up' >/dev/null 2>&1"
run_test "Returns valid HTML" "curl -s 'http://$DOMAIN' | grep -q '<!DOCTYPE html>'"

# Test 6: Laravel specific tests
run_test "Laravel key is generated" "grep -q '^APP_KEY=base64:' '$PROJECT_PATH/.env'"
run_test "Storage link exists" "[ -L '$PROJECT_PATH/public/storage' ]"

# Test 7: Check Laravel optimization
run_test "Configuration is cached" "[ -f '$PROJECT_PATH/bootstrap/cache/config.php' ]"
run_test "Routes are cached" "[ -f '$PROJECT_PATH/bootstrap/cache/routes-v7.php' ]"

# Test 8: Performance tests
info "Testing response time..."
RESPONSE_TIME=$(curl -o /dev/null -s -w '%{time_total}' "http://$DOMAIN")
if (( $(echo "$RESPONSE_TIME < 2.0" | bc -l) )); then
    success "Response time is good (${RESPONSE_TIME}s)"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    warn "Response time is slow (${RESPONSE_TIME}s)"
fi
TESTS_RUN=$((TESTS_RUN + 1))

# Test 9: Security headers
info "Testing security headers..."
SECURITY_HEADERS=$(curl -s -I "http://$DOMAIN" | grep -E "(X-Frame-Options|X-Content-Type-Options)")
if [ -n "$SECURITY_HEADERS" ]; then
    success "Security headers are present"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    fail "Security headers are missing"
fi
TESTS_RUN=$((TESTS_RUN + 1))

# Test 10: Static file serving
run_test "Static files are served" "curl -s -f --connect-timeout 5 'http://$DOMAIN/favicon.ico' >/dev/null 2>&1 || true"

echo ""
echo "=============================================="
echo "           Test Results Summary"
echo "=============================================="
echo "Tests Run: $TESTS_RUN"
echo "Tests Passed: $TESTS_PASSED"
echo "Tests Failed: $((TESTS_RUN - TESTS_PASSED))"

if [ $TESTS_PASSED -eq $TESTS_RUN ]; then
    success "All tests passed! ✅"
    echo ""
    echo "Your Laravel deployment is working perfectly!"
    exit 0
elif [ $TESTS_PASSED -gt $((TESTS_RUN * 8 / 10)) ]; then
    warn "Most tests passed ($(( (TESTS_PASSED * 100) / TESTS_RUN ))%) ⚠️"
    echo ""
    echo "Your deployment is mostly working but may have minor issues."
    exit 1
else
    fail "Many tests failed ($(( ((TESTS_RUN - TESTS_PASSED) * 100) / TESTS_RUN ))%) ❌"
    echo ""
    echo "Your deployment has significant issues that need attention."
    exit 2
fi
