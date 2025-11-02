# Server Installation Guide
## Reviewer Certificate Plugin for OJS 3.4.x

### Quick Installation for acnsci.org

---

## Files That Need Updating on Server

All files are located in: `/home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate/`

### 1. Main Plugin File
**File**: `ReviewerCertificatePlugin.inc.php`
**Changes**:
- Line 210: Added context_id
- Line 87-89: Removed deprecated Smarty code

### 2. CertificateDAO
**File**: `classes/CertificateDAO.inc.php`
**Changes**:
- Line 254: Changed `getInsertId()` to `getInsertId(): int`

### 3. CertificateSettingsForm
**File**: `classes/form/CertificateSettingsForm.inc.php`
**Changes**:
- Lines 15-18: Added form validator imports

### 4. Migration Class (NEW FILE)
**File**: `classes/migration/ReviewerCertificateInstallMigration.inc.php`
**Status**: New file - must be created
**Location**: Create directory `classes/migration/` first

### 5. Locale Files
**File 1**: `locale/en/locale.xml` (NEW)
**File 2**: `locale/en_US/locale.xml` (must exist)
**Status**: Both required

---

## Step-by-Step Installation

### Step 1: Backup Current Installation

```bash
# Backup the plugin directory
cd /home/easyscie/acnsci.org/journal/plugins/generic/
tar -czf reviewerCertificate_backup_$(date +%Y%m%d).tar.gz reviewerCertificate/

# Backup database
mysqldump -u [user] -p [database] > backup_$(date +%Y%m%d).sql
```

### Step 2: Download Latest Plugin Version

Option A - Via Git (if available):
```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/
rm -rf reviewerCertificate
git clone -b claude/ojs-reviewer-certificate-plugin-011CUhtT71BBt4guqZTJu22t [REPO_URL] reviewerCertificate
```

Option B - Manual Upload:
1. Download plugin ZIP from repository
2. Extract to local computer
3. Upload via SFTP to `/home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate/`

### Step 3: Set Permissions

```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/
chmod -R 755 reviewerCertificate/
chown -R easyscie:easyscie reviewerCertificate/
```

### Step 4: Verify All Files Present

```bash
cd /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate/

# Check critical files
ls -la ReviewerCertificatePlugin.inc.php
ls -la index.php
ls -la version.xml
ls -la schema.xml
ls -la classes/migration/ReviewerCertificateInstallMigration.inc.php
ls -la classes/CertificateDAO.inc.php
ls -la classes/Certificate.inc.php
ls -la classes/CertificateGenerator.inc.php
ls -la classes/form/CertificateSettingsForm.inc.php
ls -la controllers/CertificateHandler.inc.php
ls -la locale/en/locale.xml
ls -la locale/en_US/locale.xml
```

### Step 5: Clear OJS Cache

```bash
rm -rf /home/easyscie/acnsci.org/journal/cache/*
```

### Step 6: Enable Plugin

1. Go to: https://acnsci.org/journal/index.php/index/management/settings/website
2. Click: **Plugins** tab
3. Find: **Reviewer Certificate Plugin**
4. Click: **Enable** checkbox

### Step 7: Verify Installation

Check that these database tables were created:
```bash
mysql -u [user] -p [database] -e "SHOW TABLES LIKE 'reviewer_certificate%';"
```

Should show:
- reviewer_certificate_templates
- reviewer_certificates
- reviewer_certificate_settings

### Step 8: Configure Plugin

1. Click **Settings** next to the plugin
2. Configure:
   - Header Text: "Certificate of Recognition"
   - Body Template: (use default or customize)
   - Font Family: Choose preferred font
   - Minimum Reviews: Set to 1 or higher
   - Enable QR Code: Check if desired
3. Click **Save**

---

## Files Checklist

Use this checklist to verify all files are present and updated:

### Core Files
- [ ] `/ReviewerCertificatePlugin.inc.php` (updated)
- [ ] `/index.php`
- [ ] `/version.xml`
- [ ] `/schema.xml`
- [ ] `/README.md`
- [ ] `/OJS_3.4_COMPATIBILITY.md`
- [ ] `/INSTALL_SERVER.md`

### Class Files
- [ ] `/classes/Certificate.inc.php`
- [ ] `/classes/CertificateDAO.inc.php` (updated)
- [ ] `/classes/CertificateGenerator.inc.php`
- [ ] `/classes/form/CertificateSettingsForm.inc.php` (updated)
- [ ] `/classes/migration/ReviewerCertificateInstallMigration.inc.php` (NEW)

### Controller Files
- [ ] `/controllers/CertificateHandler.inc.php`

### Template Files
- [ ] `/templates/certificateSettings.tpl`
- [ ] `/templates/reviewerDashboard.tpl`

### Locale Files
- [ ] `/locale/en/locale.xml` (NEW)
- [ ] `/locale/en_US/locale.xml`

### Asset Files
- [ ] `/css/certificate.css`
- [ ] `/js/certificate.js`
- [ ] `/assets/README.md`

---

## Quick File Updates (If Installing Manually)

### File 1: ReviewerCertificatePlugin.inc.php

**Line 210 - Add context_id:**
```php
// Find this section around line 205:
$certificate = new Certificate();
$certificate->setReviewerId($reviewAssignment->getReviewerId());
$certificate->setSubmissionId($reviewAssignment->getSubmissionId());
$certificate->setReviewId($reviewAssignment->getId());
// ADD THIS LINE:
$certificate->setContextId(Application::get()->getRequest()->getContext()->getId());
$certificate->setDateIssued(Core::getCurrentDate());
```

**Lines 87-89 - Remove Smarty code:**
```php
// REMOVE these lines:
$templateMgr = TemplateManager::getManager($request);
$templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));

// Should look like:
case 'settings':
    $context = $request->getContext();

    $this->import('classes.form.CertificateSettingsForm');
```

### File 2: classes/CertificateDAO.inc.php

**Line 254 - Add return type:**
```php
// Change from:
public function getInsertId() {

// To:
public function getInsertId(): int {
```

### File 3: classes/form/CertificateSettingsForm.inc.php

**Lines 14-18 - Add imports:**
```php
// After this line:
import('lib.pkp.classes.form.Form');

// ADD these lines:
import('lib.pkp.classes.form.validation.FormValidator');
import('lib.pkp.classes.form.validation.FormValidatorPost');
import('lib.pkp.classes.form.validation.FormValidatorCSRF');
import('lib.pkp.classes.form.validation.FormValidatorCustom');
```

### File 4: locale/en/locale.xml

**Create this file by copying en_US:**
```bash
mkdir -p /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate/locale/en/
cp /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate/locale/en_US/locale.xml \
   /home/easyscie/acnsci.org/journal/plugins/generic/reviewerCertificate/locale/en/locale.xml

# Edit line 13 and change:
# From: <locale name="en_US" full_name="U.S. English">
# To:   <locale name="en" full_name="English">
```

---

## Troubleshooting

### Plugin Won't Enable

**Error: Class not found**
- Check all PHP files are uploaded
- Verify file permissions (755 for directories, 644 for files)
- Clear cache: `rm -rf cache/*`

**Error: Database migration failed**
- Check ReviewerCertificateInstallMigration.inc.php exists
- Verify file is in classes/migration/ directory
- Check database user has CREATE TABLE permissions

### Settings Page Won't Load

**Error: FormValidatorPost not found**
- Update CertificateSettingsForm.inc.php with import statements
- Clear cache

**Error: Missing locale key**
- Verify locale/en/locale.xml exists
- Check file has correct XML format
- Ensure locale name="en" on line 13

### Certificates Won't Generate

**Error: TCPDF not found**
- TCPDF should be included with OJS
- Check lib/pkp/lib/vendor/tecnickcom/tcpdf/ exists

**Error: Memory limit**
- Increase PHP memory_limit to 256MB or higher
- Edit php.ini or .htaccess

---

## Post-Installation Testing

### Test 1: Settings Access
1. Go to plugin settings
2. Verify form loads without errors
3. Save a test configuration

### Test 2: Create Test Certificate
1. Complete a review as a test reviewer
2. Check reviewer dashboard
3. Verify certificate download button appears

### Test 3: Download Certificate
1. Click certificate download button
2. Verify PDF generates and downloads
3. Check certificate contains correct information

### Test 4: Database Verification
```bash
mysql -u [user] -p [database]

# Check templates table
SELECT * FROM reviewer_certificate_templates;

# Check certificates table
SELECT * FROM reviewer_certificates;

# Check settings table
SELECT * FROM reviewer_certificate_settings;
```

---

## Contact & Support

For issues specific to this installation:
- Check error logs: `/home/easyscie/acnsci.org/journal/error_log`
- OJS logs: Look in lib/pkp/logs/
- PHP errors: Check server error logs

---

*Installation guide for acnsci.org - November 2, 2024*
