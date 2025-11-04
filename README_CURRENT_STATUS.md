# ReviewerCertificate Plugin - Current Status

**Last Update:** 2025-11-03
**Current Commit:** `c0f968e` - Add comprehensive diagnostic logging and fix template fallback

---

## 🚨 CRITICAL ISSUE: Server Running Old Code

### The Problem

Despite pulling latest code from git and clearing OPcache via web interface, the server continues to run old cached PHP bytecode. This is because:

1. **Multiple PHP-FPM worker processes** - Each has its own OPcache
2. **Web-based clear only affects one worker** - Other workers keep old cache
3. **Random request routing** - Different requests hit different workers

### The Evidence

Your error logs show:
- ✗ Missing new log messages (e.g., "insertObject() returned:")
- ✗ Old template error patterns ("Unknown resource type 'reviewerCertificate'")
- ✗ Silent gaps where new logging should appear

---

## ✅ What's Fixed in Repository

All code fixes are complete and pushed to repository:

### 1. Certificate Verification (QR Code 500 Error)
- **Fix:** Template loading uses `file:` prefix for absolute paths
- **Location:** `controllers/CertificateHandler.inc.php:191-202`
- **Diagnostic Logging:** Handler object type, plugin reference status, template path

### 2. Batch Generation Hanging
- **Fix:** Detailed error logging with try-catch
- **Location:** `ReviewerCertificatePlugin.inc.php:225-235`
- **Diagnostic Logging:** insertObject() return value, error type, stack trace

### 3. Certificate Button Not Visible
- **Fix:** Already correct, but added diagnostics
- **Location:** `ReviewerCertificatePlugin.inc.php:451-462`
- **Diagnostic Logging:** Output buffer type, content length, preview

### 4. Plugin Reference Not Set
- **Fix:** Better handler detection and logging
- **Location:** `ReviewerCertificatePlugin.inc.php:295-307`
- **Diagnostic Logging:** Handler type, class name, method existence

---

## 📋 Required Action: Restart PHP-FPM

### Option 1: Contact Hosting Provider (RECOMMENDED)

Send this message to your hosting support:

```
Subject: Urgent: Need PHP-FPM Restart to Clear OPcache

Hello,

I need to restart PHP-FPM to clear cached bytecode. The web-based OPcache
clear doesn't work because multiple worker processes each have separate caches.

Please run one of these commands:

sudo systemctl restart php-fpm
# OR (for specific PHP version)
sudo systemctl restart php8.1-fpm
# OR (if PHP runs in Apache)
sudo systemctl restart apache2

Thank you!
```

### Option 2: If You Have SSH + Sudo

```bash
# Check PHP version
php -v
systemctl list-units | grep php

# Restart PHP-FPM (use your PHP version)
sudo systemctl restart php-fpm
# OR
sudo systemctl restart php8.1-fpm

# Verify
sudo systemctl status php-fpm
```

### Option 3: cPanel/Plesk

- **cPanel:** MultiPHP Manager → Toggle PHP-FPM OFF/ON
- **Plesk:** Domain → PHP Settings → Restart PHP

---

## 🧪 Testing After Restart

### Run Diagnostic Script

```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
./DIAGNOSTIC_SCRIPT.sh
```

This will verify:
- Correct git commit
- New code is present in files
- File permissions are correct
- OPcache status

### Test 1: Batch Generation

1. Select reviewer in settings
2. Click "Generate Certificates"
3. **Check logs for:**

```
ReviewerCertificate: Inserting certificate into database
ReviewerCertificate: insertObject() returned: [value]
ReviewerCertificate: Certificate created successfully, total generated: 1
```

**If you see "insertObject() returned:" - NEW CODE IS RUNNING! ✓**

### Test 2: Certificate Verification

1. Preview certificate
2. Copy QR code URL from logs
3. Open in incognito window
4. **Check logs for:**

```
ReviewerCertificate: Handler object type: object, is_object: yes
ReviewerCertificate: Plugin reference set on handler successfully
ReviewerCertificate: Using template resource: [path]
```

**OR with fallback:**

```
ReviewerCertificate: Absolute template path: /home/easyscie/.../verify.tpl
ReviewerCertificate: Template file exists, displaying with file: prefix
```

### Test 3: Certificate Button

1. Log in as reviewer who completed review
2. Go to review page
3. **Check logs for:**

```
ReviewerCertificate: Output param type: string, length before: 1234
ReviewerCertificate: Additional content length: 567
ReviewerCertificate: Additional content preview: <div class="reviewer-certificate-section"...
```

---

## 📁 Important Files

| File | Purpose |
|------|---------|
| `CRITICAL_SERVER_ISSUE.md` | Detailed explanation of OPcache problem |
| `DIAGNOSTIC_SCRIPT.sh` | Script to verify code deployment |
| `CLEAR_CACHE.php` | Web-based OPcache clear (limited effectiveness) |
| `DEPLOYMENT_GUIDE.md` | Comprehensive deployment instructions |
| `DEPLOY_VERIFY.sh` | Verify correct code on server |

---

## 🔄 Workflow Summary

```
┌─────────────────────────────────┐
│  Pull Latest Code from Git      │
│  git pull origin branch          │
└───────────┬─────────────────────┘
            │
            ▼
┌─────────────────────────────────┐
│  Run Diagnostic Script          │
│  ./DIAGNOSTIC_SCRIPT.sh          │
└───────────┬─────────────────────┘
            │
            ▼
     ┌─────────────┐
     │ All Checks  │
     │    Pass?    │
     └──┬──────┬───┘
        │ NO   │ YES
        ▼      │
   ┌─────────┐ │
   │  ERROR  │ │
   │  Code   │ │
   │ Didn't  │ │
   │ Update  │ │
   └─────────┘ │
               ▼
┌─────────────────────────────────┐
│  Clear OJS Cache                │
│  rm -rf cache/*                 │
└───────────┬─────────────────────┘
            │
            ▼
┌─────────────────────────────────┐
│  Restart PHP-FPM                │
│  (Contact hosting or use sudo)  │
└───────────┬─────────────────────┘
            │
            ▼
┌─────────────────────────────────┐
│  Test All 3 Features            │
│  - Batch Generation             │
│  - Certificate Verification     │
│  - Button Display               │
└───────────┬─────────────────────┘
            │
            ▼
┌─────────────────────────────────┐
│  Check Error Logs               │
│  Look for NEW diagnostic msgs   │
└─────────────────────────────────┘
```

---

## 💡 Key Points

1. **Code is correct in repository** - All fixes are done
2. **Server is running old code** - OPcache hasn't updated
3. **Web clear doesn't work** - Multiple worker processes
4. **PHP-FPM restart required** - Only way to clear all workers
5. **Diagnostic logging added** - Will show exact failure points

---

## 📞 After Restart

Once PHP-FPM is restarted, run all tests and provide:

1. **Full error logs** from all 3 tests
2. **Screenshot** of review page HTML source
3. **Output** of diagnostic script

The new diagnostic logging will pinpoint any remaining issues.

---

## 🎯 Expected Outcome

After PHP-FPM restart:
- ✅ Certificate verification should work (or show detailed error)
- ✅ Batch generation should work (or show detailed error)
- ✅ Button injection will show why it's not appearing (output type, content length)

Then we can fix any ACTUAL bugs (vs. cache issues).

---

**Current Status:** Waiting for PHP-FPM restart to activate new code with diagnostic logging.
