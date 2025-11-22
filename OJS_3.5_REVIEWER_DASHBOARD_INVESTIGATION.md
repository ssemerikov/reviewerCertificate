# OJS 3.5 Reviewer Dashboard Investigation Guide

**Plugin Version**: 1.0.3+  
**Issue**: Certificate download button not visible in OJS 3.5  
**Status**: Under Investigation  
**Last Updated**: November 22, 2025

---

## Problem Statement

Dr. Uğur Koçak reported that the certificate download button is not visible to reviewers in OJS 3.5.0-1, although it works correctly in OJS 3.4. This suggests a template path or hook compatibility issue between OJS versions.

### What Works
✅ Plugin installs successfully in OJS 3.5  
✅ Plugin enables without errors (class loading issues fixed in v1.0.3)  
✅ Settings page loads correctly  
✅ Batch certificate generation works  
✅ Certificates are created in the database  

### What Doesn't Work
❌ Certificate download button not visible to reviewers  
❌ Reviewers cannot download their certificates from the dashboard  

---

## Root Cause Analysis

The plugin uses the `TemplateManager::display` hook to inject the certificate button into reviewer templates. The issue likely stems from one of these causes:

### 1. Template Path Changes in OJS 3.5
OJS 3.5 may use different template file paths than OJS 3.4. The plugin currently checks for:
- `reviewer/review/reviewCompleted.tpl`
- `reviewer/review/step3.tpl`
- `reviewer/review/step4.tpl`
- `reviewer/review/reviewStepHeader.tpl`

**These paths may have changed in OJS 3.5.**

### 2. Template Structure Changes
OJS 3.5 may have restructured the reviewer workflow templates, using different template variable names or rendering order.

### 3. Hook Execution Timing
The `TemplateManager::display` hook may fire at different times or with different parameters in OJS 3.5.

---

## Diagnostic Improvements in v1.0.3+

Version 1.0.3 includes comprehensive debug logging to help identify the issue:

### Added Logging
1. **All Reviewer Templates**: Logs every template displayed in the reviewer context
2. **Template Matching**: Logs when a template matches the certificate button criteria
3. **Eligibility Checks**: Logs whether the reviewer is eligible for a certificate
4. **Injection Method**: Logs which injection strategy was used (params[2] or echo)

### Added Template Paths
Additional OJS 3.5 template paths have been added to the check list:
- `reviewer/review/complete.tpl`
- `reviewer/review/reviewStep4.tpl`
- `reviewer/review/reviewComplete.tpl`

---

## Investigation Steps for Testing

### Step 1: Run the Diagnostic Tool

```bash
cd plugins/generic/reviewerCertificate
php tests/scripts/diagnose_template_paths.php
```

This tool will:
- Verify you have version 1.0.3+ with debug logging
- Show your PHP error log location
- Provide step-by-step testing instructions

### Step 2: Complete a Test Review in OJS 3.5

1. Log in as a reviewer
2. Navigate to an assigned review
3. Complete all review steps (1, 2, 3, 4)
4. Submit the review
5. Return to the review page or dashboard
6. Look for the certificate button

### Step 3: Check PHP Error Log

View the error log during the review workflow:

```bash
tail -f /path/to/php/error.log | grep ReviewerCertificate
```

**Look for these log entries:**

```
ReviewerCertificate: Template displayed: reviewer/review/[something].tpl
ReviewerCertificate: Matched template for certificate button: [template]
ReviewerCertificate: Certificate is available or reviewer is eligible - showing button
ReviewerCertificate: Injected via [method]
```

### Step 4: Analyze the Results

**Scenario A: No Templates Logged**
- The hook isn't firing at all
- Check that the plugin is enabled
- Verify error logging is enabled in PHP

**Scenario B: Templates Logged, But None Matched**
- OJS 3.5 uses different template paths
- Note which templates ARE displayed
- These need to be added to the plugin

**Scenario C: Templates Matched, But Button Not Visible**
- The injection method isn't working
- May need alternative injection approach
- Check browser console for JavaScript errors

**Scenario D: Templates Matched, Injection Logged, Still Not Visible**
- CSS may be hiding the button
- Check browser developer tools
- Verify the HTML is actually in the page source

---

## Expected Log Output

### Working Configuration (Example)

```
[22-Nov-2025 10:30:15] ReviewerCertificate: Template displayed: reviewer/review/step1.tpl
[22-Nov-2025 10:30:22] ReviewerCertificate: Template displayed: reviewer/review/step2.tpl
[22-Nov-2025 10:30:35] ReviewerCertificate: Template displayed: reviewer/review/step3.tpl
[22-Nov-2025 10:30:48] ReviewerCertificate: Template displayed: reviewer/review/step4.tpl
[22-Nov-2025 10:30:48] ReviewerCertificate: Matched template for certificate button: reviewer/review/step4.tpl
[22-Nov-2025 10:30:48] ReviewerCertificate: Certificate is available or reviewer is eligible - showing button
[22-Nov-2025 10:30:48] ReviewerCertificate: Injected via echo (output buffering)
```

### Problem Configuration (Example)

```
[22-Nov-2025 10:30:15] ReviewerCertificate: Template displayed: reviewer/workflow/step1.tpl
[22-Nov-2025 10:30:22] ReviewerCertificate: Template displayed: reviewer/workflow/step2.tpl
[22-Nov-2025 10:30:35] ReviewerCertificate: Template displayed: reviewer/workflow/step3.tpl
[22-Nov-2025 10:30:48] ReviewerCertificate: Template displayed: reviewer/workflow/completed.tpl
```

**Note**: In this problem scenario, the templates are in `reviewer/workflow/` instead of `reviewer/review/`, so they don't match!

---

## Reporting Your Findings

Please provide the following information:

### System Information
- **OJS Version**: (e.g., 3.5.0-1)
- **PHP Version**: (e.g., 8.1.2)
- **Plugin Version**: (should be 1.0.3+)
- **Web Server**: (Apache/Nginx)

### Log Data
1. Copy all "ReviewerCertificate:" log entries from your error log
2. Note which templates were displayed
3. Note whether any template was "Matched"
4. Note whether injection was attempted

### Observations
1. Can you see the certificate button? (YES/NO)
2. If you view the page source, can you find "reviewer-certificate-section"? (YES/NO)
3. Are there any JavaScript errors in browser console? (YES/NO)
4. Is the CSS file loaded? (Check Network tab in developer tools)

### Submit Report To
- **GitHub Issues**: https://github.com/ssemerikov/reviewerCertificate/issues
- **Email**: semerikov@gmail.com
- **PKP Forum**: Include link to your forum post

---

## Potential Solutions

### Solution 1: Add New Template Paths
If OJS 3.5 uses different template paths, they need to be added to the plugin code.

**File**: `ReviewerCertificatePlugin.inc.php`  
**Method**: `addCertificateButton()`  
**Line**: ~409

Add the new template paths to the `$reviewerTemplates` array.

### Solution 2: Alternative Hook
Use a different hook that's more reliable in OJS 3.5:
- `LoadComponentHandler`
- `TemplateResource::getFilename`
- Custom page handler via `LoadHandler`

### Solution 3: Dashboard Widget
Create a dashboard widget instead of using template hooks (OJS 3.5 approach).

### Solution 4: Direct Template Override
Override the OJS 3.5 reviewer template to include the certificate button natively.

---

## Quick Fixes to Try

### Quick Fix 1: Enable All Reviewer Templates

Try enabling the button on ALL reviewer-related templates:

**Edit**: `ReviewerCertificatePlugin.inc.php` line ~430

**Change**:
```php
if (!in_array($template, $reviewerTemplates)) {
    return false;
}
```

**To**:
```php
// Temporary: Match ANY reviewer template
if (strpos($template, 'reviewer/') !== 0) {
    return false;
}
```

This will attempt to show the button on every reviewer page. Check the logs to see which template displays the button successfully.

### Quick Fix 2: Force Button Output

Add the button HTML directly to a known page via the `LoadHandler` hook instead of template hooks.

**This requires custom code modification** - contact semerikov@gmail.com for assistance.

---

## Developer Notes

### Template Hook Limitations

The `TemplateManager::display` hook has some limitations:
1. Fires for EVERY template, requiring careful filtering
2. Output parameter (`$params[2]`) may be NULL in some contexts
3. Direct `echo` relies on output buffering being enabled
4. Template paths vary between OJS versions

### Alternative Approaches

**Approach 1: LoadHandler Hook**
Create a custom page handler for certificates, then link to it from reviewer dashboard.

**Approach 2: Dashboard Notifications**
Use OJS notification system to alert reviewers about available certificates.

**Approach 3: Email Notification**
Send email with certificate download link (already partially implemented).

**Approach 4: My Account Page**
Add certificates to the user's "My Account" page instead of reviewer dashboard.

---

## Next Steps

1. **Dr. Koçak**: Please run the diagnostic tool and provide log output
2. **Developer**: Analyze logs to identify correct OJS 3.5 template paths
3. **Developer**: Implement fix based on findings
4. **Dr. Koçak**: Test updated version
5. **Release**: Version 1.0.4 with OJS 3.5 template compatibility

---

## Contact & Support

**Plugin Author**: Serhiy O. Semerikov  
**Email**: semerikov@gmail.com  
**GitHub**: https://github.com/ssemerikov/reviewerCertificate  
**PKP Forum**: https://forum.pkp.sfu.ca/

**Special Thanks**: Dr. Uğur Koçak for thorough testing and detailed bug reports

---

**Last Updated**: November 22, 2025  
**Document Version**: 1.0  
**Plugin Version**: 1.0.3+
