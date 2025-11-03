# IMMEDIATE ACTION REQUIRED - ReviewerCertificate Plugin

## Current Status

✅ **ALL CODE FIXES ARE COMPLETE** in repository
❌ **SERVER IS RUNNING OLD CACHED PHP CODE**

**Current Commit:** `bc47304` - Add web-accessible OPcache clearing script
**Branch:** `claude/fix-certificate-handler-null-error-011CUmFQjwopq2qMUiebZjd5`

---

## Why Your Tests Are Still Failing

Your server has **PHP OPcache enabled**, which caches PHP bytecode in memory. Even though you've pulled the latest code from the repository, PHP is still executing the **old cached version** from memory.

**Evidence:**
- Error logs show `Undefined variable $rowData` on line 223
- But the actual code on line 223 uses `$row` (not `$rowData`)
- This proves the server is running OLD code from cache

---

## SOLUTION: Clear PHP OPcache (3 Easy Steps)

### Step 1: Pull Latest Code
```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
git pull origin claude/fix-certificate-handler-null-error-011CUmFQjwopq2qMUiebZjd5
```

### Step 2: Clear PHP OPcache via Web Interface
1. Open your web browser
2. Go to: **https://acnsci.org/journal/plugins/generic/reviewerCertificate/CLEAR_CACHE.php**
3. Click the blue button: **"Clear OPcache Now"**
4. Wait for success message: "✅ OPcache cleared successfully!"

### Step 3: Clear OJS Template Cache
```bash
cd /home/easyscie/acnsci.org/journal
rm -rf cache/t_cache/*
rm -rf cache/t_compile/*
```

### Step 4: Hard Refresh Browser
Press `Ctrl+Shift+R` (Windows/Linux) or `Cmd+Shift+R` (Mac)

---

## CRITICAL SECURITY WARNING

**🔒 DELETE CLEAR_CACHE.php IMMEDIATELY AFTER USE!**

```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
rm CLEAR_CACHE.php
```

**Why?** This file allows anyone to clear your server's PHP cache and should not remain accessible.

---

## What Should Happen After Clearing Cache

### ✅ Test 1: Preview Certificate
- Go to: Website Settings > Plugins > Reviewer Certificate > Settings
- Click "Preview Certificate"
- **Expected:** PDF downloads successfully
- **NOT Expected:** Fatal error about PKPComponentRouter

### ✅ Test 2: QR Code URL Format
- Check error log after preview
- **Expected:** `https://acnsci.org/journal/index.php/cte/certificate/verify/PREVIEW12345`
- **NOT Expected:** Missing `/index.php/` in URL

### ✅ Test 3: Certificate Verification (Public Access)
- Copy QR code URL from logs
- Open in **incognito window** (logged out)
- **Expected:** Certificate verification page loads (shows "Certificate Invalid" for PREVIEW code)
- **NOT Expected:** "Authorization Denied" error

### ✅ Test 4: Batch Generation
- Select a reviewer who has completed reviews
- Click "Generate Certificates"
- **Expected:** "1 certificate(s) generated successfully"
- **Check logs - should see:**
  ```
  ReviewerCertificate: Processing reviewer ID: 59
  ReviewerCertificate: Executing SQL query for reviewer 59
  ReviewerCertificate: Creating certificate for review_id: 663
  ReviewerCertificate: Inserting certificate into database
  ReviewerCertificate: Certificate created successfully
  ```
- **NOT Expected:** Any `$rowData` errors or "Duplicate entry '0'" errors

### ✅ Test 5: Settings Form Redirect
- Upload new background image
- Click save
- **Expected:** Redirects to `.../management/settings/website#plugins`
- **NOT Expected:** `#Array` in URL or Masthead tab

### ✅ Test 6: Reviewer Dashboard
- Sign in as reviewer who completed a review
- **Expected:** Page loads without errors
- **Expected:** Blue certificate download button visible
- **NOT Expected:** Fatal error about `->next()` on array

---

## If Still Having Issues After Clearing OPcache

### Option A: Verify Deployment
```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
./DEPLOY_VERIFY.sh
```

All checks should show "✓ CORRECT".

### Option B: Contact Hosting Provider
If clearing OPcache via the web script doesn't work, you need hosting provider to:
1. Restart PHP-FPM service: `sudo systemctl restart php-fpm`
2. OR restart web server: `sudo systemctl restart apache2`

Tell them: _"The website is serving cached PHP bytecode from OPcache. I need to clear the cache or restart PHP-FPM to load new code files."_

---

## All Fixes Included in Current Version

1. ✅ **QR Code URL** - Now includes `/index.php/` in URL
2. ✅ **Certificate Verification** - Public access, no login required
3. ✅ **Array Iteration** - Handles both OJS 3.3 and 3.4
4. ✅ **Form Redirect** - Goes to Plugins tab, not Masthead
5. ✅ **Preview Certificate** - No PKPComponentRouter error
6. ✅ **Template Path** - Uses absolute path correctly
7. ✅ **Batch Generation** - Uses `$row` not `$rowData`

---

## Quick Reference: Files Changed

| File | Fix |
|------|-----|
| `ReviewerCertificatePlugin.inc.php:359-377` | Array iteration for OJS 3.4 |
| `ReviewerCertificatePlugin.inc.php:126` | Form redirect to plugins tab |
| `ReviewerCertificatePlugin.inc.php:214-221` | Batch generation uses `$row` |
| `classes/CertificateGenerator.inc.php:278-284` | Manual QR URL construction |
| `controllers/CertificateHandler.inc.php:49-51` | Public verify access |
| `controllers/CertificateHandler.inc.php:175` | Absolute template path |
| `locale/en/locale.po:35-36` | Added missing locale key |

---

## Summary

**The code is fixed. The server is running old code from cache.**

**Action Required:**
1. Access: https://acnsci.org/journal/plugins/generic/reviewerCertificate/CLEAR_CACHE.php
2. Click "Clear OPcache Now"
3. Delete CLEAR_CACHE.php file
4. Clear OJS template cache
5. Test all functionality
6. Report results

**Expected Result:** All 6 tests should pass after clearing OPcache.
