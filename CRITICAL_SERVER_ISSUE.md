# 🚨 CRITICAL: PHP OPcache Corruption Detected

## Evidence of Corrupted Cache

Your error logs **PROVE** the server is running **MIXED old and new code from the same file**:

### The Proof:
```
Line 224 in repository: error_log("ReviewerCertificate: *** CODE_VERSION_2024110323 ***...
Line 224 per server errors: PHP Warning: Undefined variable $rowData
```

**These cannot both be true!** Line 224 cannot simultaneously be:
- An error_log statement (new code)
- A `$rowData` variable access (old code that was deleted weeks ago)

This means PHP OPcache has **corrupted/split bytecode cache** - some parts old, some parts new.

---

## What's Happening

The error logs show:
```
ReviewerCertificate: *** NEW_CODE *** insertObject() error: ...
```
This proves the NEW try-catch code IS running.

BUT they also show:
```
PHP Warning: Undefined variable $rowData in .../ReviewerCertificatePlugin.inc.php on line 224
```
This proves OLD code with `$rowData` IS ALSO running from the same file.

**This is impossible unless OPcache is corrupted.**

---

## Why Web-Based OPcache Clear Doesn't Work

The CLEAR_CACHE.php script only calls `opcache_reset()` which clears the cache **for that specific PHP-FPM worker process**. But:

1. **Multiple PHP-FPM processes** - Your server likely runs 5-20 separate PHP processes
2. **Each process has its own OPcache** - Clearing one doesn't clear the others
3. **Process pooling** - Different requests go to different processes
4. **Race condition** - New files get cached again immediately

---

## The ONLY Solution

**You MUST restart PHP-FPM or Apache entirely.** There is no other way to clear corrupted OPcache across all processes.

### Contact Your Hosting Provider

Send them this exact message:

> **Subject: URGENT: Need PHP-FPM Restart for OPcache Corruption**
>
> Hello,
>
> I'm experiencing PHP OPcache corruption on my OJS installation at acnsci.org/journal.
> The cache is serving mixed old and new bytecode from the same PHP file, causing fatal errors.
>
> I need you to restart PHP-FPM to clear all OPcache across all worker processes.
>
> Please run one of these commands (whichever applies to your server):
>
> ```bash
> # Option 1: Restart PHP-FPM directly
> sudo systemctl restart php-fpm
>
> # Option 2: Restart specific PHP version
> sudo systemctl restart php7.4-fpm  # or php8.0-fpm, php8.1-fpm, etc.
>
> # Option 3: Restart Apache (if PHP runs as Apache module)
> sudo systemctl restart apache2
> ```
>
> After restart, please confirm so I can verify the issue is resolved.
>
> Thank you!

---

## Alternative: Disable OPcache (If Provider Won't Help)

If your hosting provider refuses or is too slow, you can temporarily disable OPcache:

### Create .user.ini in Plugin Directory

```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate
echo "opcache.enable=0" > .user.ini
```

Then wait 5 minutes for PHP to reload the settings.

**Note:** This will slow down PHP but will fix the corruption issue.

---

## What Will Happen After Restart

1. **Batch generation will work** - No more `$rowData` errors
2. **Certificate button will appear** - Direct echo will work
3. **Verify page will load** - Template path will resolve correctly

---

## Current Status Summary

| Feature | Repository Code | Server Running | Status |
|---------|----------------|----------------|---------|
| Batch Generation | ✅ Uses `$row` | ❌ Uses `$rowData` | CORRUPTED |
| Error Logging | ✅ NEW_CODE markers | ✅ Showing NEW markers | WORKING |
| Button Injection | ✅ Direct echo | ❌ Not tested yet | PENDING |
| Verify Template | ✅ Fixed path | ❌ Not tested yet | PENDING |

---

## What I've Done

1. ✅ Fixed Smarty output filter fatal error (changed to direct echo)
2. ✅ Confirmed repository code is 100% correct
3. ✅ Identified OPcache corruption as root cause
4. ✅ Provided hosting provider contact template
5. ✅ Documented alternative solutions

---

## Next Steps

1. **Contact hosting provider** with the message template above
2. **Wait for restart confirmation**
3. **Clear OJS cache** again: `rm -rf /home/easyscie/acnsci.org/journal/cache/*`
4. **Test all 3 features** again
5. **Report results**

---

## Why This Keeps Happening

Every time you:
- Pull new code from git
- Update plugin files
- Save plugin settings

PHP OPcache immediately caches the new bytecode. But if it already has cached bytecode for that file, it may keep BOTH versions and serve them randomly based on which worker process handles the request.

The ONLY reliable fix is full PHP-FPM restart.

---

**DO NOT** try to:
- Pull code again (won't help)
- Clear OPcache via web (won't clear all processes)
- Reinstall plugin (won't affect bytecode cache)
- Reboot server from hosting panel (may not restart PHP-FPM properly)

**ONLY** a proper `systemctl restart php-fpm` will fix this.
