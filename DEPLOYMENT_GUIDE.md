# ReviewerCertificate Plugin - Deployment Guide

## Current Version Info
**Branch:** `claude/fix-certificate-handler-null-error-011CUmFQjwopq2qMUiebZjd5`
**Latest Commit:** `d026466` - "Add deployment verification script"
**Previous Commit:** `06877e5` - "Fix certificate preview and form redirect errors"

---

## All Fixes Included in This Version

### 1. ✅ QR Code URL Format (FIXED)
- **Issue:** Missing `/index.php/` in URL
- **Was:** `https://acnsci.org/journal/cte/certificate/verify/...`
- **Now:** `https://acnsci.org/journal/index.php/cte/certificate/verify/...`
- **File:** `classes/CertificateGenerator.inc.php:282`

### 2. ✅ Certificate Verification Authorization (FIXED)
- **Issue:** "Authorization Denied" when accessing QR code URL
- **Fix:** Public access for verify() operation - no login required
- **File:** `controllers/CertificateHandler.inc.php:49-51`

### 3. ✅ Array Iteration Error (FIXED)
- **Issue:** `Fatal error: Call to member function next() on array`
- **Fix:** Handle both array (OJS 3.4) and DAOResultFactory (OJS 3.3)
- **File:** `ReviewerCertificatePlugin.inc.php:359-377`

### 4. ✅ Form Redirect to Wrong Page (FIXED)
- **Issue:** Redirects to Masthead tab showing `#Array` in URL
- **Fix:** Redirects to Website Settings > Plugins tab
- **File:** `ReviewerCertificatePlugin.inc.php:126`

### 5. ✅ Preview Certificate Fatal Error (FIXED)
- **Issue:** `Path must be null when calling PKPComponentRouter::url()`
- **Fix:** Use manual URL construction instead of $request->url()
- **File:** `classes/CertificateGenerator.inc.php:278-284`

### 6. ✅ Template Path Error (FIXED)
- **Issue:** `Smarty: Unable to load template`
- **Fix:** Use absolute path with Core::getBaseDir()
- **File:** `controllers/CertificateHandler.inc.php:175`

---

## CRITICAL: Server Deployment Steps

### Why You're Still Seeing Old Errors

Your server is **running cached PHP bytecode from old commits**. The error logs show `$rowData` errors which don't exist in the current code.

### Step-by-Step Deployment

#### 1. Navigate to Plugin Directory
```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
```

#### 2. Fetch Latest Code
```bash
# Fetch all branches
git fetch origin

# Switch to the correct branch
git checkout claude/fix-certificate-handler-null-error-011CUmFQjwopq2qMUiebZjd5

# Pull latest changes
git pull origin claude/fix-certificate-handler-null-error-011CUmFQjwopq2qMUiebZjd5

# Verify you're on the right commit
git log --oneline -1
# Should show: d026466 Add deployment verification script
```

#### 3. Run Verification Script
```bash
# This checks that correct code is deployed
./DEPLOY_VERIFY.sh
```

**Expected output:**
```
1. Current branch:
* claude/fix-certificate-handler-null-error-011CUmFQjwopq2qMUiebZjd5

2. Current commit:
d026466 Add deployment verification script

3. Line 223 (batch generation):
error_log("ReviewerCertificate: Inserting certificate into database");
   ✓ CORRECT (should NOT mention $rowData)

4. QR code URL generation:
$baseUrl = $request->getBaseUrl();
$verificationUrl = $baseUrl . '/index.php/' . $contextPath . '/certificate/verify/' . $code;
   ✓ CORRECT (manual construction with /index.php/)

5. Form redirect:
$request->redirect(null, 'management', 'settings', 'website', 'plugins');
   ✓ CORRECT (anchor is 'plugins' string, not array)

6. Uncommitted changes:
   ✓ No uncommitted changes
```

#### 4. Clear PHP OPcache (CRITICAL!)

**Choose ONE based on your server setup:**

**Option A: PHP-FPM (Most Common)**
```bash
# For PHP 7.4
sudo systemctl restart php7.4-fpm

# For PHP 8.0
sudo systemctl restart php8.0-fpm

# For PHP 8.1
sudo systemctl restart php8.1-fpm

# Generic (if you don't know version)
sudo systemctl restart php-fpm
```

**Option B: Apache with mod_php**
```bash
sudo systemctl restart apache2
```

**Option C: Via PHP Script (if no root access)**
1. Create file: `/home/easyscie/acnsci.org/journal/clear_opcache.php`
```php
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully!";
} else {
    echo "OPcache not available";
}
?>
```
2. Access: `https://acnsci.org/journal/clear_opcache.php`
3. **DELETE the file after use** (security risk!)

#### 5. Clear OJS Cache
```bash
cd /home/easyscie/acnsci.org/journal
rm -rf cache/t_cache/*
rm -rf cache/t_compile/*
```

#### 6. Restart Web Server (Optional but Recommended)
```bash
sudo systemctl restart apache2
# OR
sudo systemctl restart nginx
```

#### 7. Clear Browser Cache
- Hard refresh: `Ctrl+Shift+R` (Windows/Linux) or `Cmd+Shift+R` (Mac)
- OR open incognito/private window

---

## Testing Checklist

### ✅ Test 1: Verify Deployment
```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
./DEPLOY_VERIFY.sh
```
All checks should pass.

### ✅ Test 2: Preview Certificate
1. Log in as journal manager
2. Go to: Website Settings > Plugins > Reviewer Certificate > Settings
3. Click "Preview Certificate"
4. **Expected:** PDF downloads successfully
5. **NOT Expected:** Fatal error about PKPComponentRouter

### ✅ Test 3: QR Code URL Format
1. Open the preview certificate PDF
2. Check error log for: `ReviewerCertificate: QR code URL:`
3. **Expected:** `https://acnsci.org/journal/index.php/cte/certificate/verify/PREVIEW12345`
4. **NOT Expected:** Missing `/index.php/` in URL

### ✅ Test 4: Certificate Verification (Public Access)
1. Copy QR code URL from logs
2. Open in **incognito window** (not logged in)
3. **Expected:** Certificate verification page loads
4. **Expected:** Shows "Certificate Invalid" for PREVIEW code
5. **NOT Expected:** "Authorization Denied" error

### ✅ Test 5: Form Redirect
1. Upload new background image
2. Click save
3. **Expected:** Redirects to `...management/settings/website#plugins`
4. **NOT Expected:** `#Array` in URL or redirect to Masthead tab

### ✅ Test 6: Batch Generation
1. Go to plugin settings
2. Select a reviewer who has completed reviews
3. Click "Generate Certificates"
4. **Expected:** Success message "1 certificate(s) generated successfully"
5. **Check logs:** Should show complete batch flow with NO `$rowData` errors

Expected log output:
```
ReviewerCertificate: Processing reviewer ID: 59
ReviewerCertificate: Executing SQL query for reviewer 59
ReviewerCertificate: SQL query executed, result type: object
ReviewerCertificate: Creating certificate for review_id: 663
ReviewerCertificate: Inserting certificate into database
ReviewerCertificate: Certificate created successfully, total generated: 1
ReviewerCertificate: Batch generation completed - generated 1 certificates
```

### ✅ Test 7: Reviewer Page
1. Sign in as a reviewer who completed a review
2. Go to their review dashboard
3. **Expected:** Page loads without fatal errors
4. **Check logs:** Should see "Review assignments is array with X items"
5. **NOT Expected:** Fatal error about `->next()` on array

### ✅ Test 8: Certificate Button Visibility
1. As reviewer, go to completed review page
2. Look for certificate download section
3. **Expected:** Blue box with "Your Certificate is Ready!" heading
4. **If not visible:** Right-click > View Page Source, search for `reviewer-certificate-wrapper`
5. If HTML exists but not visible: CSS issue (will investigate)
6. If HTML doesn't exist: Report back with full error logs

---

## Troubleshooting

### Issue: Still seeing `$rowData` errors
**Cause:** PHP is still running old cached bytecode
**Fix:** Restart PHP-FPM/Apache (step 4 above)

### Issue: Preview Certificate still fails
**Cause:** Old code still deployed
**Fix:** Verify git commit is `06877e5` or later, run verification script

### Issue: QR code URL missing `/index.php/`
**Cause:** Old CertificateGenerator.inc.php
**Fix:** Check line 282 should have manual URL construction

### Issue: Certificate button not visible
**Possible causes:**
1. CSS hiding it - check browser inspector
2. Template caching - clear OJS cache
3. JavaScript removing it - check browser console for errors
4. Template not being injected - check error logs for "Certificate button added successfully"

---

## Expected Error Log Patterns (After Fix)

**✅ GOOD - These should appear:**
```
ReviewerCertificate: QR code URL: https://acnsci.org/journal/index.php/cte/certificate/verify/PREVIEW12345
ReviewerCertificate: Review assignments is array with 7 items
ReviewerCertificate: Found ReviewAssignment (ID: 4295) for user 1
ReviewerCertificate: Certificate button added successfully
ReviewerCertificate: Batch generation completed - generated 1 certificates
```

**❌ BAD - These should NOT appear:**
```
Undefined variable $rowData
Call to a member function next() on array
Path must be null when calling PKPComponentRouter::url()
authorizationDenied (after certificate/verify)
Array to string conversion (in PKPPageRouter)
```

---

## Summary of Changes by Commit

### d026466 - Add deployment verification script
- Added `DEPLOY_VERIFY.sh` to verify correct deployment

### 06877e5 - Fix certificate preview and form redirect errors
- Fixed QR code URL to use manual construction with `/index.php/`
- Fixed form redirect anchor from array to 'plugins' string

### f9dda39 - Fix ALL critical issues - COMPREHENSIVE FIX
- Fixed array iteration to handle both arrays and DAOResultFactory
- Fixed authorization to allow public access for verify()
- Fixed form redirect destination

### 42169b8 - Fix certificate verification template path error
- Fixed template loading to use absolute path

### 5d81b00 - Fix critical certificate handler null error and improve logging
- Enhanced batch generation logging
- Added missing locale key

---

## Support

If after following all steps you still see issues:

1. Run verification script and send output:
   ```bash
   cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
   ./DEPLOY_VERIFY.sh > verify_output.txt 2>&1
   ```

2. Check what PHP version is actually running:
   ```bash
   php -v
   systemctl list-units | grep php
   ```

3. Send recent error log entries (after deployment):
   ```bash
   tail -100 /path/to/error.log | grep ReviewerCertificate
   ```

4. Verify file permissions:
   ```bash
   ls -la ReviewerCertificatePlugin.inc.php
   ls -la classes/CertificateGenerator.inc.php
   ```

All these issues have been fixed in the code. The remaining step is proper deployment on the server.
