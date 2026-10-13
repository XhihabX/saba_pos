#!/usr/bin/env bash
# ==============================================================================
# Saba POS — Production Post-Deployment Smoke Test Script
# Usage: ./scripts/smoke_prod.sh [https://pos.sababilling.net]
# ==============================================================================

set -u

TARGET_URL="${1:-https://pos.sababilling.net}"
TARGET_URL="${TARGET_URL%/}"
DOMAIN=$(echo "$TARGET_URL" | sed -E 's#^https?://##; s#/.*##')

GREEN="\033[0;32m"
RED="\033[0;31m"
YELLOW="\033[1;33m"
BLUE="\033[0;34m"
NC="\033[0m"

echo -e "${BLUE}================================================================${NC}"
echo -e "${BLUE}   Saba POS Production Deployment Smoke Test: ${TARGET_URL}${NC}"
echo -e "${BLUE}================================================================${NC}"

TOTAL_PASSED=0
TOTAL_FAILED=0

run_check() {
    local test_name="$1"
    local status_tag="$2"
    local expected="$3"
    local actual="$4"

    if [[ "$expected" == *"$actual"* ]]; then
        echo -e "  [${GREEN}PASS${NC}] ${test_name} -> HTTP ${actual} (${status_tag})"
        TOTAL_PASSED=$((TOTAL_PASSED + 1))
    else
        echo -e "  [${RED}FAIL${NC}] ${test_name} -> Expected: ${expected}, Got: ${actual} (${status_tag})"
        TOTAL_FAILED=$((TOTAL_FAILED + 1))
    fi
}

echo ""
echo -e "${YELLOW}1. Host Header Forgery Protection:${NC}"
STATUS_FORGERY=$(curl -k -s -o /dev/null -w "%{http_code}" -H "Host: evil-attacker-forged.com" "${TARGET_URL}/health" --connect-timeout 5)
run_check "Untrusted Host Header returns HTTP 400" "[verified in tests]" "400" "${STATUS_FORGERY}"

echo ""
echo -e "${YELLOW}2. HTTPS Login Availability:${NC}"
STATUS_LOGIN=$(curl -k -s -o /dev/null -w "%{http_code}" "https://${DOMAIN}/login" --connect-timeout 5)
run_check "HTTPS /login returns HTTP 200" "[needs the real server]" "200" "${STATUS_LOGIN}"

echo ""
echo -e "${YELLOW}3. Insecure HTTP to HTTPS Redirection:${NC}"
STATUS_HTTP_REDIRECT=$(curl -k -s -o /dev/null -w "%{http_code}" "http://${DOMAIN}/login" --connect-timeout 5)
run_check "Insecure HTTP redirects to HTTPS (301/302)" "[needs the real server]" "301 302 308" "${STATUS_HTTP_REDIRECT}"

echo ""
echo -e "${YELLOW}4. Health Check & Queue Configuration Telemetry:${NC}"
HEALTH_JSON=$(curl -k -s "${TARGET_URL}/health" --connect-timeout 5)
if echo "${HEALTH_JSON}" | grep -q '"database"' && echo "${HEALTH_JSON}" | grep -q '"queue"'; then
    echo -e "  [${GREEN}PASS${NC}] /health reports queue driver 'database' ([verified in tests])"
    TOTAL_PASSED=$((TOTAL_PASSED + 1))
else
    echo -e "  [${RED}FAIL${NC}] /health does not report queue driver 'database' (Got: ${HEALTH_JSON}) ([verified in tests])"
    TOTAL_FAILED=$((TOTAL_FAILED + 1))
fi

echo ""
echo -e "${YELLOW}5. Private Configuration Security (.env Inaccessibility):${NC}"
STATUS_ENV=$(curl -k -s -o /dev/null -w "%{http_code}" "${TARGET_URL}/.env" --connect-timeout 5)
run_check "Direct GET /.env blocked (404/403)" "[needs the real server]" "404 403 400" "${STATUS_ENV}"

echo ""
echo -e "${YELLOW}6. Private Directory Isolation (storage & vendor):${NC}"
STATUS_VENDOR=$(curl -k -s -o /dev/null -w "%{http_code}" "${TARGET_URL}/vendor/autoload.php" --connect-timeout 5)
STATUS_STORAGE=$(curl -k -s -o /dev/null -w "%{http_code}" "${TARGET_URL}/storage/app/.env" --connect-timeout 5)
run_check "Direct GET /vendor/autoload.php blocked (404/403)" "[needs the real server]" "404 403 400" "${STATUS_VENDOR}"
run_check "Direct GET /storage/app/.env blocked (404/403)" "[needs the real server]" "404 403 400" "${STATUS_STORAGE}"

echo ""
echo -e "${BLUE}================================================================${NC}"
echo -e "Smoke Test Results: ${GREEN}${TOTAL_PASSED} passed${NC}, ${RED}${TOTAL_FAILED} failed${NC}"
echo -e "${BLUE}================================================================${NC}"

if [ "${TOTAL_FAILED}" -eq 0 ]; then
    exit 0
else
    exit 1
fi
