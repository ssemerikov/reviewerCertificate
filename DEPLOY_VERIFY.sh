#!/bin/bash
# Deployment Verification Script
# This script verifies that the correct code version is deployed

echo "==================================="
echo "ReviewerCertificate Deploy Checker"
echo "==================================="
echo ""

# 1. Check current branch
echo "1. Current branch:"
git branch | grep '\*'
echo ""

# 2. Check current commit
echo "2. Current commit:"
git log --oneline -1
echo "   Expected: 06877e5 Fix certificate preview and form redirect errors"
echo ""

# 3. Check critical line 223 in ReviewerCertificatePlugin.inc.php
echo "3. Line 223 (batch generation):"
sed -n '223p' ReviewerCertificatePlugin.inc.php
echo "   Expected: error_log(\"ReviewerCertificate: Inserting certificate into database\");"
echo "   NOT: anything with \$rowData"
echo ""

# 4. Check QR code URL generation method
echo "4. QR code URL generation (lines 278-282):"
sed -n '278,282p' classes/CertificateGenerator.inc.php
echo "   Expected: Should use \$baseUrl . '/index.php/' . \$contextPath"
echo "   NOT: \$request->url()"
echo ""

# 5. Check redirect anchor parameter
echo "5. Form redirect (line 126):"
sed -n '126p' ReviewerCertificatePlugin.inc.php
echo "   Expected: 'plugins' (string)"
echo "   NOT: array()"
echo ""

# 6. Check for uncommitted changes
echo "6. Uncommitted changes:"
git status --short
if [ -z "$(git status --short)" ]; then
    echo "   ✓ No uncommitted changes"
else
    echo "   ✗ WARNING: Uncommitted changes found!"
fi
echo ""

# 7. Verify file modification times
echo "7. Last modified times:"
echo "   ReviewerCertificatePlugin.inc.php: $(stat -c %y ReviewerCertificatePlugin.inc.php 2>/dev/null || stat -f %Sm ReviewerCertificatePlugin.inc.php)"
echo "   CertificateGenerator.inc.php: $(stat -c %y classes/CertificateGenerator.inc.php 2>/dev/null || stat -f %Sm classes/CertificateGenerator.inc.php)"
echo ""

echo "==================================="
echo "VERIFICATION COMPLETE"
echo "==================================="
echo ""
echo "If all checks pass, restart PHP:"
echo "  sudo systemctl restart php-fpm"
echo "  OR"
echo "  sudo systemctl restart apache2"
echo ""
echo "Then clear OJS cache:"
echo "  cd /home/easyscie/acnsci.org/journal"
echo "  rm -rf cache/t_cache/* cache/t_compile/*"
