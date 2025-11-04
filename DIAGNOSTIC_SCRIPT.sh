#!/bin/bash
# Diagnostic script to verify ReviewerCertificate plugin deployment
# Run this on the server after pulling latest code

echo "================================"
echo "ReviewerCertificate Plugin Diagnostic"
echo "================================"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate

echo "1. Checking Git Status..."
echo "--------------------------"
CURRENT_COMMIT=$(git log --oneline -1)
echo "Current commit: $CURRENT_COMMIT"

EXPECTED_COMMIT="c0f968e"
if echo "$CURRENT_COMMIT" | grep -q "$EXPECTED_COMMIT"; then
    echo -e "${GREEN}✓ Correct commit${NC}"
else
    echo -e "${RED}✗ NOT on expected commit!${NC}"
    echo "Expected: c0f968e Add comprehensive diagnostic logging and fix template fallback"
    echo ""
    echo "Run: git pull origin claude/fix-certificate-handler-null-error-011CUmFQjwopq2qMUiebZjd5"
    exit 1
fi
echo ""

echo "2. Verifying Code Content..."
echo "----------------------------"

# Check for new diagnostic logging
if grep -q "insertObject() returned:" ReviewerCertificatePlugin.inc.php; then
    echo -e "${GREEN}✓ Batch generation logging present${NC}"
else
    echo -e "${RED}✗ Batch generation logging MISSING${NC}"
    echo "File may not have updated!"
fi

if grep -q "Handler object type:" ReviewerCertificatePlugin.inc.php; then
    echo -e "${GREEN}✓ Handler diagnostic logging present${NC}"
else
    echo -e "${RED}✗ Handler diagnostic logging MISSING${NC}"
fi

if grep -q "Output param type:" ReviewerCertificatePlugin.inc.php; then
    echo -e "${GREEN}✓ Button injection logging present${NC}"
else
    echo -e "${RED}✗ Button injection logging MISSING${NC}"
fi

if grep -q "file: prefix" controllers/CertificateHandler.inc.php; then
    echo -e "${GREEN}✓ Template fallback fix present${NC}"
else
    echo -e "${RED}✗ Template fallback fix MISSING${NC}"
fi

echo ""

echo "3. Checking File Permissions..."
echo "--------------------------------"
if [ -r "ReviewerCertificatePlugin.inc.php" ]; then
    echo -e "${GREEN}✓ Plugin file readable${NC}"
else
    echo -e "${RED}✗ Plugin file NOT readable${NC}"
fi

if [ -r "controllers/CertificateHandler.inc.php" ]; then
    echo -e "${GREEN}✓ Handler file readable${NC}"
else
    echo -e "${RED}✗ Handler file NOT readable${NC}"
fi

if [ -r "templates/verify.tpl" ]; then
    echo -e "${GREEN}✓ Verify template readable${NC}"
else
    echo -e "${RED}✗ Verify template NOT readable${NC}"
fi

echo ""

echo "4. Checking OJS Cache..."
echo "------------------------"
CACHE_DIR="/home/easyscie/acnsci.org/journal/cache"
if [ -d "$CACHE_DIR/t_cache" ]; then
    CACHE_COUNT=$(find "$CACHE_DIR/t_cache" -type f 2>/dev/null | wc -l)
    echo "Template cache files: $CACHE_COUNT"
    if [ $CACHE_COUNT -gt 0 ]; then
        echo -e "${YELLOW}⚠ Template cache not empty - should clear${NC}"
    else
        echo -e "${GREEN}✓ Template cache empty${NC}"
    fi
else
    echo "Template cache directory not found"
fi

echo ""

echo "5. PHP Version Check..."
echo "-----------------------"
PHP_VERSION=$(php -v 2>/dev/null | head -n 1)
if [ $? -eq 0 ]; then
    echo "$PHP_VERSION"
else
    echo "PHP CLI not available or in different path"
fi

echo ""

echo "6. OPcache Status..."
echo "--------------------"
# Check if opcache is enabled
php -r "echo opcache_get_status() ? 'OPcache: ENABLED' : 'OPcache: DISABLED';" 2>/dev/null
echo ""
echo "Note: Web-based OPcache clear only affects one PHP-FPM worker."
echo "For full clear, PHP-FPM service must be restarted."

echo ""
echo "================================"
echo "Summary"
echo "================================"

echo ""
echo "Next Steps:"
echo "1. If any checks FAILED above, code didn't update properly"
echo "2. Clear OJS cache: rm -rf $CACHE_DIR/*"
echo "3. Clear OPcache: Visit CLEAR_CACHE.php"
echo "4. If tests still fail, contact hosting to restart PHP-FPM"
echo ""
echo "Test After Clearing:"
echo "- Batch Generation: Should log 'insertObject() returned'"
echo "- Certificate Verify: Should log 'Handler object type'"
echo "- Button Injection: Should log 'Output param type'"
echo ""
echo "If you don't see these NEW log messages, PHP-FPM needs restart!"
