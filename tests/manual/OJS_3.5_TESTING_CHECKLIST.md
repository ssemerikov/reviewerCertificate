# OJS 3.5 Manual Testing Checklist
# Reviewer Certificate Plugin v1.0.3

**Tester**: _______________  
**Date**: _______________  
**OJS Version**: 3.5.0-___  
**PHP Version**: _______________  
**Environment**: [ ] Development  [ ] Staging  [ ] Production

---

## Pre-Installation Checks

- [ ] OJS 3.5.x is installed and running correctly
- [ ] PHP 8.0 or higher is installed
- [ ] Required PHP extensions are available:
  - [ ] mbstring
  - [ ] gd or imagick
  - [ ] zip
  - [ ] mysqli or pdo_mysql
- [ ] Database access is available
- [ ] Write permissions are set correctly on plugins directory

---

## Installation Testing

### Step 1: Upload Plugin

**Method**: [ ] Upload via UI  [ ] Git clone  [ ] Manual copy

**Actions**:
- [ ] Download or copy plugin to `plugins/generic/reviewerCertificate/`
- [ ] Set correct file permissions (755 for directories, 644 for files)
- [ ] Verify all files are present (check version.xml exists)

**Expected Result**: Plugin files are in correct location

**Result**: [ ] Pass  [ ] Fail

**Notes**:
```
_____________________________________________________
_____________________________________________________
```

---

### Step 2: Enable Plugin

**Actions**:
- [ ] Log in as Journal Manager or Site Administrator
- [ ] Navigate to: Settings → Website → Plugins
- [ ] Find "Reviewer Certificate Plugin" under Generic Plugins
- [ ] Click checkbox to enable plugin

**Expected Result**: 
- Plugin enables without errors
- Success message appears: "The plugin 'Reviewer Certificate Plugin' has been enabled"
- **CRITICAL**: NO errors in PHP error log

**Result**: [ ] Pass  [ ] Fail

**Check PHP Error Log**:
```bash
tail -f /path/to/php/error.log
```

**Errors Found** (if any):
```
_____________________________________________________
_____________________________________________________
```

**CRITICAL CHECKS** (v1.0.3 fixes):
- [ ] NO "Class 'DataObject' not found" error
- [ ] NO "Class 'Certificate' not found" error
- [ ] NO "Plugin ReviewerCertificatePlugin failed to be registered" error

---

### Step 3: Verify Database Tables Created

**Actions**:
- [ ] Connect to database
- [ ] Check for table: `reviewer_certificates`
- [ ] Check for table: `reviewer_certificate_templates`
- [ ] Check for table: `reviewer_certificate_settings`

**SQL Queries**:
```sql
SHOW TABLES LIKE 'reviewer_certificate%';
DESC reviewer_certificates;
```

**Expected Result**: All 3 tables exist with correct schema

**Result**: [ ] Pass  [ ] Fail

**Table Verification**:
- [ ] `reviewer_certificates` has columns: certificate_id, reviewer_id, submission_id, review_id, context_id, template_id, date_issued, certificate_code, download_count, last_downloaded
- [ ] Indexes are created correctly
- [ ] Primary keys are set

---

### Step 4: Access Plugin Settings

**Actions**:
- [ ] On Plugins page, click "Settings" button next to plugin name
- [ ] Settings modal should open

**Expected Result**: 
- Settings form loads successfully
- **NO** "Failed Ajax request or invalid JSON returned" error
- Form displays all fields:
  - [ ] Header Text
  - [ ] Body Template with template variables
  - [ ] Footer Text
  - [ ] Font Family dropdown
  - [ ] Font Size input
  - [ ] Text Color (RGB) inputs
  - [ ] Background Image upload
  - [ ] Include QR Code checkbox
  - [ ] Minimum Reviews input

**Result**: [ ] Pass  [ ] Fail

**Screenshots**: (attach if any issues)

---

### Step 5: Configure Plugin Settings

**Actions**:
- [ ] Set Header Text: "Certificate of Recognition"
- [ ] Set Body Template (use default or custom):
  ```
  This certificate is awarded to
  
  {{$reviewerName}}
  
  In recognition of their valuable contribution as a peer reviewer for
  
  {{$journalName}}
  
  Review completed on {{$reviewDate}}
  
  Manuscript: {{$submissionTitle}}
  ```
- [ ] Set Footer Text (optional): "Valid certificate - verify at {{$verificationUrl}}"
- [ ] Select Font Family: Helvetica
- [ ] Set Font Size: 12
- [ ] Set Text Color: R=0, G=0, B=0 (black)
- [ ] Upload background image (optional, A4 size recommended)
- [ ] Set Minimum Reviews: 1
- [ ] Check "Include QR Code": Yes
- [ ] Click "Save"

**Expected Result**: 
- Form saves successfully
- Success message appears
- Settings persist after refreshing page

**Result**: [ ] Pass  [ ] Fail

---

### Step 6: Preview Certificate

**Actions**:
- [ ] In settings form, click "Preview Certificate" button

**Expected Result**:
- PDF preview opens in new tab/window
- PDF displays with:
  - [ ] Header text
  - [ ] Body text with sample data (John Doe, Sample Journal, etc.)
  - [ ] Footer text
  - [ ] Background image (if uploaded)
  - [ ] QR code (if enabled)
  - [ ] Proper formatting and alignment

**Result**: [ ] Pass  [ ] Fail

**PDF Quality Issues** (if any):
```
_____________________________________________________
_____________________________________________________
```

---

## Certificate Generation Testing

### Step 7: Create Test Review

**Pre-requisites**:
- [ ] At least one submission exists in the journal
- [ ] At least one reviewer user exists

**Actions**:
- [ ] Assign a review to a reviewer
- [ ] Log in as the reviewer
- [ ] Complete the review (submit recommendation)
- [ ] Return to dashboard

**Expected Result**: Review is completed successfully

**Result**: [ ] Pass  [ ] Fail

---

### Step 8: Verify Certificate Availability (Reviewer View)

**Actions**:
- [ ] As reviewer, navigate to completed review
- [ ] Check for certificate download button/link

**Expected Result**:
- [ ] "Download Certificate" button is visible
- [ ] Button is clickable
- [ ] **CRITICAL**: Reviewer can see the certificate option

**Result**: [ ] Pass  [ ] Fail

**Issue** (if button not visible):
```
_____________________________________________________
_____________________________________________________
```

---

### Step 9: Download Certificate (Reviewer)

**Actions**:
- [ ] Click "Download Certificate" button

**Expected Result**:
- PDF downloads successfully
- PDF contains:
  - [ ] Reviewer's actual name
  - [ ] Actual journal name
  - [ ] Actual submission title
  - [ ] Correct review completion date
  - [ ] Unique certificate code (12 characters)
  - [ ] QR code (if enabled)
- PDF formatting is correct
- Certificate code is unique

**Result**: [ ] Pass  [ ] Fail

**Certificate Code**: _______________

**PDF Issues** (if any):
```
_____________________________________________________
_____________________________________________________
```

---

### Step 10: Batch Certificate Generation (Journal Manager)

**Actions**:
- [ ] Log in as Journal Manager
- [ ] Navigate to: Settings → Website → Plugins
- [ ] Click "Settings" on Reviewer Certificate Plugin
- [ ] Scroll to "Batch Certificate Generation" section
- [ ] Select one or more reviewers from the list
- [ ] Click "Generate Certificates"

**Expected Result**:
- Success message shows: "Generated X certificate(s)"
- **NO** "Class 'Certificate' not found" error
- **NO** timeout errors
- Certificates are created in database

**Result**: [ ] Pass  [ ] Fail

**Certificates Generated**: _______________

**Errors in PHP log**:
```
_____________________________________________________
_____________________________________________________
```

---

### Step 11: Verify Certificates in Database

**Actions**:
- [ ] Connect to database
- [ ] Run query:
  ```sql
  SELECT * FROM reviewer_certificates ORDER BY date_issued DESC LIMIT 10;
  ```

**Expected Result**:
- Certificates exist in table
- All required fields are populated:
  - [ ] certificate_id (auto-increment)
  - [ ] reviewer_id (valid user ID)
  - [ ] submission_id (valid submission)
  - [ ] review_id (valid review assignment)
  - [ ] context_id (valid journal context)
  - [ ] date_issued (current date)
  - [ ] certificate_code (12 char alphanumeric)
  - [ ] download_count (0 initially)

**Result**: [ ] Pass  [ ] Fail

**Sample Row** (copy one row data):
```
_____________________________________________________
_____________________________________________________
```

---

### Step 12: Certificate Verification

**Actions**:
- [ ] Note certificate code from downloaded PDF or database
- [ ] Navigate to: `https://your-journal.com/index.php/journal-path/certificate/verify/[CERTIFICATE_CODE]`
- [ ] OR scan QR code from certificate

**Expected Result**:
- Verification page loads
- Displays certificate details:
  - [ ] Reviewer name
  - [ ] Journal name
  - [ ] Submission title
  - [ ] Date issued
  - [ ] Certificate code
  - [ ] Verification status: "Valid"

**Result**: [ ] Pass  [ ] Fail

**Verification URL**: _______________

---

## Regression Testing (Ensure No Breaking Changes)

### Step 13: Test with Multiple Reviewers

**Actions**:
- [ ] Create certificates for 5+ different reviewers
- [ ] Verify each reviewer can only see their own certificates
- [ ] Verify download counts increment correctly

**Expected Result**: Access control works correctly

**Result**: [ ] Pass  [ ] Fail

---

### Step 14: Test Certificate Re-Download

**Actions**:
- [ ] Download same certificate multiple times
- [ ] Check database for download_count and last_downloaded

**Expected Result**:
- Download count increments with each download
- last_downloaded timestamp updates
- Same certificate PDF is generated each time

**Result**: [ ] Pass  [ ] Fail

---

### Step 15: Test Background Image Upload

**Actions**:
- [ ] Upload a background image (JPG, PNG, GIF)
- [ ] Save settings
- [ ] Generate new certificate
- [ ] Check PDF has background image

**Expected Result**: Background image displays correctly in PDF

**Result**: [ ] Pass  [ ] Fail

**Image Format Tested**: [ ] JPG  [ ] PNG  [ ] GIF

---

### Step 16: Test Large Batch Generation

**Actions**:
- [ ] Select 20+ reviewers for batch generation
- [ ] Monitor PHP error log during generation
- [ ] Check for timeouts or memory errors

**Expected Result**: 
- All certificates generate successfully
- No timeout errors
- No memory exhaustion

**Result**: [ ] Pass  [ ] Fail

**Performance**:
- Number of certificates: _______________
- Generation time: _______________
- Any errors: _______________

---

## OJS 3.5 Specific Compatibility Tests

### Step 17: Class Loading Verification

**Actions**:
- [ ] Enable PHP error reporting (set `display_errors = On` temporarily)
- [ ] Refresh plugins page multiple times
- [ ] Check error log for any class loading errors

**Expected Result**: 
- **NO** "Class 'DataObject' not found" errors
- **NO** "Class 'Certificate' not found" errors
- **NO** "Plugin failed to be registered" errors

**Result**: [ ] Pass  [ ] Fail

**Error Log Check**:
```bash
grep -i "dataobject\|certificate.*not found" /path/to/php/error.log
```

**Errors Found**: [ ] None  [ ] Some (list below)

```
_____________________________________________________
_____________________________________________________
```

---

### Step 18: DAO Operations Test

**Actions**:
- [ ] Perform various database operations:
  - [ ] Insert certificate (via batch generate)
  - [ ] Retrieve certificate (via download)
  - [ ] Update certificate (via download - increments count)
  - [ ] Query certificates (via reviewer dashboard)

**Expected Result**: All DAO operations work without errors

**Result**: [ ] Pass  [ ] Fail

---

### Step 19: Hook System Test

**Actions**:
- [ ] Complete a new review
- [ ] Verify certificate button appears on reviewer dashboard
- [ ] Check that hook `TemplateManager::display` is working

**Expected Result**: Hooks execute correctly, button appears

**Result**: [ ] Pass  [ ] Fail

---

## Final Checks

### Step 20: Multi-Journal Testing (if applicable)

**Actions**:
- [ ] If multi-journal OJS installation, test on 2+ journals
- [ ] Verify certificates are journal-specific
- [ ] Verify settings are journal-specific

**Expected Result**: Each journal has independent certificate system

**Result**: [ ] Pass  [ ] Fail  [ ] N/A (single journal)

---

### Step 21: Disable and Re-Enable Plugin

**Actions**:
- [ ] Disable plugin
- [ ] Check that reviewer dashboard no longer shows certificate button
- [ ] Re-enable plugin
- [ ] Verify button reappears
- [ ] Verify settings persist

**Expected Result**: Plugin enables/disables cleanly

**Result**: [ ] Pass  [ ] Fail

---

### Step 22: Uninstall Test (Optional - USE TEST ENVIRONMENT ONLY)

**Actions**:
- [ ] **BACKUP DATABASE FIRST**
- [ ] Note number of certificates in database
- [ ] Uninstall plugin (if uninstall option available)
- [ ] Check if tables are removed (optional - depends on uninstall behavior)

**Expected Result**: Plugin uninstalls cleanly

**Result**: [ ] Pass  [ ] Fail  [ ] Not tested

---

## Summary

**Total Tests**: 22  
**Passed**: _____  
**Failed**: _____  
**Skipped**: _____  

**Overall Result**: [ ] PASS - Ready for production  [ ] FAIL - Issues need resolution

---

## Critical Issues Found

**List any critical issues** (blocking production deployment):

1. _____________________________________________________
2. _____________________________________________________
3. _____________________________________________________

---

## Non-Critical Issues Found

**List any minor issues** (can be addressed in future releases):

1. _____________________________________________________
2. _____________________________________________________
3. _____________________________________________________

---

## Recommendations

**Deployment Recommendation**: [ ] Approved  [ ] Conditional  [ ] Not Approved

**Conditions** (if conditional):
```
_____________________________________________________
_____________________________________________________
```

**Additional Notes**:
```
_____________________________________________________
_____________________________________________________
_____________________________________________________
```

---

## Sign-Off

**Tester Name**: _______________  
**Signature**: _______________  
**Date**: _______________  

**Reviewed By**: _______________  
**Signature**: _______________  
**Date**: _______________  

---

**End of Testing Checklist**
