# Changelog

All notable changes to the Reviewer Certificate Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-01-05

### Changed - OJS 3.5 Full Compatibility Update
This release brings full OJS 3.5 compatibility while maintaining backward compatibility with OJS 3.3 and 3.4.

#### Breaking Changes from OJS 3.5 Addressed
- **File Extension**: Renamed all `.inc.php` files to `.php` (OJS 3.5 requirement)
- **Namespaces**: Added PSR-4 namespaces to all plugin classes
- **import() Deprecated**: Replaced with PHP `use` statements
- **fatalError() Removed**: Replaced with `throw new Exception()`
- **Hook Registration**: Updated to use `PKP\plugins\Hook` class for OJS 3.5

#### Files Renamed
| Old Name | New Name |
|----------|----------|
| `ReviewerCertificatePlugin.inc.php` | `ReviewerCertificatePlugin.php` |
| `controllers/CertificateHandler.inc.php` | `controllers/CertificateHandler.php` |
| `classes/Certificate.inc.php` | `classes/Certificate.php` |
| `classes/CertificateDAO.inc.php` | `classes/CertificateDAO.php` |
| `classes/CertificateGenerator.inc.php` | `classes/CertificateGenerator.php` |
| `classes/form/CertificateSettingsForm.inc.php` | `classes/form/CertificateSettingsForm.php` |
| `classes/migration/ReviewerCertificateInstallMigration.inc.php` | `classes/migration/ReviewerCertificateInstallMigration.php` |

#### Namespaces Added
- `APP\plugins\generic\reviewerCertificate` - Main plugin
- `APP\plugins\generic\reviewerCertificate\controllers` - Request handlers
- `APP\plugins\generic\reviewerCertificate\classes` - Core classes
- `APP\plugins\generic\reviewerCertificate\classes\form` - Form classes
- `APP\plugins\generic\reviewerCertificate\classes\migration` - Database migrations

### Technical Details
- **Backward Compatible**: All changes include fallback patterns for OJS 3.3/3.4
- **No database changes** - Safe upgrade with no migration required
- **PHP 8.2+**: Tested with PHP 8.2 as required by OJS 3.5
- **Note**: The `reviewassignmentdao::_updateobject` hook is deprecated in OJS 3.5; auto-email on review completion may not work in OJS 3.5

---

## [1.0.8] - 2026-01-05

### Fixed
- **Critical: OJS 3.5 getLocalizedTitle() Error** - Fixed HTTP 500 error when downloading certificates
  - **Issue**: `Call to undefined method APP\submission\Submission::getLocalizedTitle()`
  - **Root Cause**: OJS 3.5 removed `getLocalizedTitle()` from Submission class; must use Publication object
  - **Solution**: Added 5 helper methods with `method_exists()` checks and fallback logic
  - **Files Modified**: `classes/CertificateGenerator.inc.php`
  - **Reported by**: @drugurkocak (GitHub Issue #57)

### Technical Details
- **New Helper Methods**:
  - `getSubmissionTitle()` - Uses `getCurrentPublication()->getLocalizedTitle()` for OJS 3.5
  - `getReviewerGivenName()` / `getReviewerFamilyName()` - User name retrieval with fallbacks
  - `getContextName()` / `getContextAcronym()` - Journal info retrieval with fallbacks
- **Backward Compatible**: Works correctly in OJS 3.3, 3.4, and 3.5
- **No database changes** - Safe upgrade with no migration required

---

## [1.0.7] - 2025-01-04

### Fixed
- **Critical: OJS 3.5 URL Parameter Type Error** - Fixed TypeError preventing certificate button display
  - **Issue**: `PKP\core\PKPRequest::url(): Argument #4 ($path) must be of type ?array, int given`
  - **Root Cause**: OJS 3.5 has stricter type checking; `$path` parameter must be an array, not an integer
  - **Solution**: Wrapped review assignment ID in array for all `$request->url()` calls
  - **Files Modified**: `ReviewerCertificatePlugin.inc.php` (lines 529, 658, 660)
  - **Reported by**: @drugurkocak (GitHub Issue #57)

- **Critical: OJS 3.5 Handler Registration Error** - Fixed HANDLER_CLASS deprecation error
  - **Issue**: `The use of HANDLER_CLASS is no longer supported for injecting handlers`
  - **Root Cause**: OJS 3.5 removed support for legacy `define('HANDLER_CLASS', ...)` pattern
  - **Solution**: Use direct handler assignment via reference (`$handler =& $params[3]`) for OJS 3.5+
  - **Key Fixes**:
    - Use `array_key_exists(3, $params)` instead of `isset()` (isset returns false for null)
    - Use reference assignment `$handler =& $params[3]` per PKP Plugin Guide
  - **Files Modified**: `ReviewerCertificatePlugin.inc.php` - `setupHandler()` method
  - **Reported by**: @drugurkocak (GitHub Issue #57)

### Technical Details
- Line 529: `$request->url(null, 'certificate', 'download', $id)` → `array($id)`
- Line 658: Same fix for email template URL
- Line 660: Changed `$request->url($context->getPath())` to `$request->getBaseUrl() . '/' . $context->getPath()`
- `setupHandler()`: OJS 3.5 uses `$params[3] = new CertificateHandler()`; OJS 3.3/3.4 uses `define('HANDLER_CLASS', ...)`
- **Backward Compatible**: Works correctly in OJS 3.3, 3.4, and 3.5
- **No database changes** - Safe upgrade with no migration required

---

## [1.0.6] - 2025-01-04

### Added
- **12 New Language Translations** - Expanded global coverage from ~87% to ~95% of OJS user base
  - **Tier 3 Priority Languages (7)**:
    - Chinese Simplified (`zh_CN`) - 简体中文
    - Arabic (`ar_AR`) - العربية (RTL support)
    - Japanese (`ja_JP`) - 日本語
    - Korean (`ko_KR`) - 한국어
    - Persian/Farsi (`fa_IR`) - فارسی (RTL support)
    - Greek (`el_GR`) - Ελληνικά
    - Hebrew (`he_IL`) - עברית (RTL support)
  - **Medium Priority Languages (5)**:
    - Hungarian (`hu_HU`) - Magyar
    - Lithuanian (`lt_LT`) - Lietuvių
    - Slovak (`sk_SK`) - Slovenčina
    - Slovenian (`sl_SI`) - Slovenščina
    - Bulgarian (`bg_BG`) - Български (Cyrillic script)

- **RTL (Right-to-Left) Language Support** - Full support for Arabic, Persian, and Hebrew
  - OJS handles RTL display automatically
  - All 83 message keys properly translated

- **CJK Language Support** - Chinese, Japanese, and Korean translations
  - DejaVu Sans font recommended for proper PDF rendering
  - Full UTF-8 encoding for multi-byte characters

- **Additional Cyrillic Script Support** - Bulgarian translations following Russian locale patterns

### Technical Details
- **Files Added**: 24 new files (12 directories × 2 files each)
  - `locale/{lang}/locale.xml` for OJS 3.3/3.4 compatibility
  - `locale/{lang}/locale.po` for OJS 3.5 compatibility
- **Total Languages**: 32 (up from 20)
- **Message Keys**: 82 per XML file, 83 per PO file (including email templates)
- **All translations validated** with comprehensive test suite
- **No database changes** - Safe upgrade with no migration required
- **No configuration changes** - All existing settings preserved

---

## [1.0.5] - 2025-01-03

### Fixed
- **Date Format Display Error** - Certificate verification page showed `%B %e, %Y` instead of formatted date
  - **Issue**: Smarty's `date_format` modifier uses deprecated `strftime()` function
  - **Root Cause**: PHP 8.1+ deprecated `strftime()`, PHP 8.2+ shows format codes instead of dates
  - **Solution**: Format date in PHP using `date('F j, Y')` before passing to template
  - **Files Modified**:
    - `controllers/CertificateHandler.inc.php` - Line 224: Format date in verify() method
    - `templates/verify.tpl` - Line 25: Removed `date_format` Smarty modifier
  - **Reported by**: Olha P. Pinchuk

- **Memory Exhaustion Issue** - Missing return statement after error in download handler
  - **Issue**: `fatalError()` doesn't halt execution, code continued running after error
  - **Root Cause**: Missing `return` statement after `fatalError()` call in download() method
  - **Solution**: Added `return;` after `fatalError()` on line 148
  - **File Modified**: `controllers/CertificateHandler.inc.php`
  - **Reported by**: Olha P. Pinchuk

### Technical Details
- **PHP 8.1+ Compatibility**: Replaced deprecated `strftime()` with modern `date()` function
- **Backward Compatible**: Works correctly on PHP 7.3+ through PHP 8.2+
- **No Database Changes**: Safe upgrade with no migration required

---

## [1.0.4] - 2025-01-03

### Added
- **`.po` Locale Files for OJS 3.5** - Added Gettext `.po` format locale files for all 20 languages
  - **Issue**: OJS 3.5 only recognizes `.po` files, not `.xml` files for translations
  - **Root Cause**: OJS 3.5 switched to Gettext format for locale files
  - **Solution**: Generated `.po` files for all locales alongside existing `.xml` files
  - **Files Added**: `locale/*/locale.po` for all 20 supported languages
  - **Reported by**: Pedro Felipe Rocha (PKP Community Forum)

- **Brazilian Portuguese Translation** - New `pt_BR` locale with complete translation
  - Community contribution by Pedro Felipe Rocha
  - 83 message keys translated with professional scholarly terminology
  - Both `.xml` and `.po` formats included

### Changed
- **Documentation Updates**:
  - Updated README.md with locale file format information
  - Added simpler installation instructions (tar.gz upload method)
  - Updated INSTALL.md with OJS Admin upload instructions
  - Added CLAUDE.md with development documentation

### Technical Details
- **Locale File Formats**:
  - `.xml` files for OJS 3.3/3.4 compatibility
  - `.po` files for OJS 3.5+ compatibility
- **All 20 Languages Updated**: en, en_US, uk_UA, ru_RU, es_ES, pt_BR, fr_FR, de_DE, it_IT, tr_TR, pl_PL, id_ID, nl_NL, cs_CZ, ca_ES, nb_NO, sv_SE, hr_HR, fi_FI, ro_RO

### Community Feedback Addressed
- Pedro Felipe Rocha: "OJS 3.5 only recognizes .po files" - **FIXED** with dual format support
- Pedro Felipe Rocha: "Translations showing ### symbols" - **FIXED** with .po file generation

---

## [1.0.3] - 2025-11-24

### Fixed
- **Critical: Font Size Setting Not Applied** - Fixed certificate font size configuration being ignored during PDF generation
  - **Issue**: Users could configure fontSize in plugin settings, but it had no effect on generated certificates
  - **Root Cause**: All text elements in PDF used hardcoded font sizes (Header: 24pt, Body: 14pt, Footer: 10pt, Code: 8pt, QR: 6pt)
  - **Solution**: Implemented proportional font size scaling based on configured fontSize setting
  - **Proportional Scaling**:
    - Header: 2.0× base size (e.g., 12pt → 24pt, 16pt → 32pt)
    - Body: 1.167× base size (e.g., 12pt → 14pt, 16pt → 19pt)
    - Footer: 0.833× base size (e.g., 12pt → 10pt, 16pt → 13pt)
    - Certificate Code: 0.667× base size (e.g., 12pt → 8pt, 16pt → 11pt)
    - QR Label: 0.5× base size (e.g., 12pt → 6pt, 16pt → 8pt)
  - **Files Modified**:
    - `classes/CertificateGenerator.inc.php` - Lines 213-218, 226, 236, 247, 255, 300-301, 304
  - **Impact**: Font size setting now works correctly; all text elements scale proportionally while maintaining visual hierarchy
  - **Reported by**: Dr. Pavlo Nechypurenko

### Added
- **Test Coverage**: New comprehensive test `testProportionalFontSizes()` validates proportional font size calculations
  - Tests 5 different base font sizes (8pt, 10pt, 12pt, 16pt, 18pt)
  - Verifies all proportional calculations are correct
  - Ensures visual hierarchy is maintained (header > body > footer > code > QR label)
  - File: `tests/Unit/CertificateGeneratorTest.php` - Lines 190-226

### Changed
- **Font Size Behavior**: Font size setting now applies globally to all certificate text elements with proportional scaling
  - Previously: fontSize setting was saved but ignored (hardcoded values always used)
  - Now: fontSize setting controls all text sizes proportionally
  - Default behavior unchanged (fontSize=12 produces same output as before)
  - Enhanced flexibility: Users can now increase/decrease all text sizes by changing one setting

### Technical Details
- **Commits**: 1 commit (e58bc2a)
- **Files Modified**: 2 files
  - `classes/CertificateGenerator.inc.php` - Font size calculation logic
  - `tests/Unit/CertificateGeneratorTest.php` - Proportional font size tests
- **Lines Changed**: +54 added, -5 removed
- **Backward Compatible**: Default fontSize=12 produces identical output to v1.0.2
- **No Database Changes**: Safe upgrade with no migration required
- **No Configuration Changes**: All existing settings work without modification

---

## [1.0.2] - 2025-11-22

### Fixed
- **Critical: OJS 3.5 Compatibility** - Fixed "Call to undefined function import()" errors preventing plugin installation
  - Replaced all deprecated `import()` function calls with proper namespace use statements
  - Updated ReviewerCertificatePlugin.inc.php to use modern PHP namespaces
  - Updated CertificateDAO.inc.php with proper PKP\db namespace imports
  - Updated CertificateHandler.inc.php with proper APP\handler namespace
  - Updated CertificateSettingsForm.inc.php with proper PKP\form namespaces
  - Files: All core plugin files (.inc.php)
- **Critical: Class Loading Issue** - Fixed "Plugin expected to inherit from ReviewerCertificatePlugin, actual type NULL" error
  - Changed parent class references to use fully qualified class names instead of `use` imports
  - Ensures proper class loading order across all OJS versions
  - Parent classes now use backslash notation: `extends \PKP\plugins\GenericPlugin`
  - Prevents autoloader race conditions that could cause NULL instantiation

### Changed
- **Modern PHP Namespacing**: Plugin now uses PSR-4 compliant namespace imports instead of legacy import() function
  - Uses fully qualified parent class names: `extends \PKP\plugins\GenericPlugin`
  - Uses `use` statements only for utility classes (JSONMessage, LinkAction, MailTemplate, etc.)
  - Uses `require_once()` for plugin-specific class loading
  - Maintains backward compatibility with OJS 3.3 and 3.4

### Technical Details
- All core plugin files updated to work with OJS 3.5.0+ which removed the deprecated import() function
- Plugin class loading now uses `require_once($this->getPluginPath() . '/classes/...')`  pattern
- Parent classes use fully qualified names to prevent autoloader issues
- Proper use statements for PKP library utility classes (JSONMessage, LinkAction, MailTemplate, etc.)

### Community Feedback Addressed
This release addresses the critical installation issue reported by Dr. Uğur Koçak on PKP Community Forum:
- "Error: Call to undefined function import()" - **FIXED** with namespace refactoring
- Plugin now loads successfully in OJS 3.5.0-1 and later versions

---

## [1.0.1] - 2025-11-16

### Added
- **OJS 3.5 Compatibility**: Official support declaration in plugin metadata (`version.xml`)
- **Automatic Migration Fallback**: Dual-strategy database installation that automatically falls back to raw SQL if Laravel Schema facade fails (OJS 3.3 compatibility)
- **Manual SQL Installation Scripts**:
  - `install.sql` - Complete database setup for manual installation
  - `uninstall.sql` - Clean removal of plugin tables
- **Comprehensive Installation Guide**: New `INSTALL.md` with detailed instructions and troubleshooting
- **Error Message Localization**: New locale strings for database and context errors
- **Version Compatibility Table**: Clear documentation of support status for each OJS version

### Fixed
- **Critical: AJAX Settings Form Error** - Fixed "Failed Ajax request or invalid JSON returned" error when clicking Settings button
  - Added NULL checks after `DAORegistry::getDAO()` calls to prevent fatal errors
  - Added context validation in all AJAX endpoints (`settings`, `preview`, `generateBatch`)
  - Added exception handling for `Repo::user()->get()` calls
  - Added error handling in form `initData()` method with fallback to default values
  - Files: `ReviewerCertificatePlugin.inc.php`, `CertificateSettingsForm.inc.php`
- **Critical: Database Migration Failures in OJS 3.3** - Tables not being created during automatic installation
  - Root cause: Laravel Schema facade not available/initialized in OJS 3.3
  - Solution: Migration now tries Schema facade first, then automatically falls back to raw SQL via DAO
  - Error logged with detailed messages for troubleshooting
  - File: `classes/migration/ReviewerCertificateInstallMigration.inc.php`

### Changed
- **Migration Strategy**: Enhanced to support both modern (OJS 3.4+) and legacy (OJS 3.3) installation methods
  - Uses `upWithSchema()` for OJS 3.4+ (Laravel migration)
  - Uses `upWithRawSQL()` for OJS 3.3 (direct SQL via DAO)
  - Both `up()` and `down()` methods support automatic fallback
- **Documentation**: Extensively updated README.md and added INSTALL.md
  - Requirements section now lists all three supported OJS versions
  - Added version compatibility table
  - Updated development section mentioning comprehensive testing (121 tests)
  - Added PHP version recommendations (8.0+ for OJS 3.5)

### Technical Details
- **Commits**:
  - `16d80c6` - Add manual SQL installation scripts and comprehensive INSTALL guide
  - `292064a` - Fix AJAX "invalid JSON" error in Settings form
  - `ed656a6` - Add automatic OJS 3.3 fallback to database migration
  - `5322a75` - Declare official OJS 3.5 compatibility in plugin metadata
- **Testing**: All existing 121 tests passing across OJS 3.3, 3.4, 3.5
- **PHP Compatibility**: Tested on PHP 7.3, 7.4, 8.0, 8.1, 8.2

### Community Feedback Addressed
This release addresses multiple issues reported on PKP Community Forum:
- Dr. Uğur Koçak: "Table 'reviewer_certificates' doesn't exist" - **FIXED** with SQL fallback
- Jricst: "Failed Ajax request or invalid JSON returned" - **FIXED** with null checks
- Marc: "plugin don't register it's tables" - **FIXED** with automatic fallback migration
- Pedro Felipe Rocha: "needs a version compatible with OJS 3.5" - **ADDED** official support

---

## [1.0.0] - 2025-11-04

### Added
- Initial release of Reviewer Certificate Plugin
- Automated certificate generation for completed reviews
- Customizable certificate templates with background images, fonts, and colors
- Dynamic content insertion using template variables
- QR code verification support
- Certificate download tracking and statistics
- Batch certificate generation
- Multi-language support
- Full compatibility with OJS 3.3 and 3.4
- Comprehensive test suite (121 tests)
- GitHub Actions CI/CD pipeline
- Bundled TCPDF library (v6.10.0)

### Features
- Certificate template customization
- Eligibility criteria (minimum reviews)
- Automatic certificate availability after review completion
- Certificate verification system
- Download statistics and analytics
- Reviewer dashboard integration
- Admin batch generation interface

---

## Version History Summary

| Version | Date | Type | Key Changes |
|---------|------|------|-------------|
| 1.1.0 | 2026-01-05 | Minor | Full OJS 3.5 compatibility - PSR-4 namespaces, .php extensions |
| 1.0.7 | 2025-01-04 | Patch | Fixed OJS 3.5 URL parameter type error (Issue #57) |
| 1.0.6 | 2025-01-04 | Minor | Added 12 new languages (zh_CN, ar_AR, ja_JP, ko_KR, fa_IR, el_GR, he_IL, hu_HU, lt_LT, sk_SK, sl_SI, bg_BG) |
| 1.0.5 | 2025-01-03 | Patch | Date format fix (PHP 8.1+), memory issue fix |
| 1.0.4 | 2025-01-03 | Patch | Added .po locale files for OJS 3.5, Brazilian Portuguese translation |
| 1.0.3 | 2025-11-24 | Patch | Font size setting fix - now applies proportionally to all text |
| 1.0.2 | 2025-11-22 | Patch | OJS 3.5 compatibility fix - removed deprecated import() calls |
| 1.0.1 | 2025-11-16 | Patch | Critical bug fixes, OJS 3.5 support, improved installation |
| 1.0.0 | 2025-11-04 | Major | Initial release |

---

## Upgrade Notes

### From 1.0.7 to 1.1.0
- **File renames**: All `.inc.php` files renamed to `.php` (OJS 3.5 requirement)
- **Namespaces added**: All classes now use PSR-4 namespaces
- **No database changes** - Safe upgrade with no migration required
- **No configuration changes** - All existing settings preserved
- **Backward compatible**: Works correctly in OJS 3.3, 3.4, and 3.5
- **Recommended**: Delete old `.inc.php` files after upgrade to avoid conflicts
- **Recommended**: Clear OJS cache after upgrade (`php tools/upgrade.php check`)

### From 1.0.6 to 1.0.7
- **No database changes** - Safe to upgrade without data migration
- **No configuration changes** - All existing settings preserved
- **Automatic**: Simply replace plugin files and refresh cache
- **Critical for OJS 3.5**: This update fixes the certificate button not appearing for reviewers
- **Recommended**: Clear OJS cache after upgrade (`php tools/upgrade.php check`)

### From 1.0.5 to 1.0.6
- **No database changes** - Safe to upgrade without data migration
- **No configuration changes** - All existing settings preserved
- **Automatic**: Simply replace plugin files and refresh cache
- **New languages available**: 12 additional translations ready to use
- **Recommended**: Clear OJS cache after upgrade (`php tools/upgrade.php check`)

### From 1.0.4 to 1.0.5
- **No database changes** - Safe to upgrade without data migration
- **No configuration changes** - All existing settings preserved
- **Automatic**: Simply replace plugin files and refresh cache
- **Critical for PHP 8.1+**: This update fixes date format display on verification page
- **Recommended**: Clear OJS cache after upgrade (`php tools/upgrade.php check`)

### From 1.0.3 to 1.0.4
- **No database changes** - Safe to upgrade without data migration
- **No configuration changes** - All existing settings preserved
- **Automatic**: Simply replace plugin files and refresh cache
- **Critical for OJS 3.5**: This update is REQUIRED for translations to work in OJS 3.5
- **New locale files**: `.po` files added for all 20 languages
- **Recommended**: Clear OJS cache after upgrade (`php tools/upgrade.php check`)

### From 1.0.2 to 1.0.3
- **No database changes** - Safe to upgrade without data migration
- **No configuration changes** - All existing settings preserved
- **Automatic**: Simply replace plugin files and refresh cache
- **Backward compatible**: Default fontSize=12 produces identical output to v1.0.2
- **Recommended**: Review font size setting after upgrade - it now works correctly
- **Optional**: Clear OJS cache after upgrade (`php tools/upgrade.php check`)

### From 1.0.1 to 1.0.2
- **No database changes** - Safe to upgrade without data migration
- **No configuration changes** - All existing settings preserved
- **Automatic**: Simply replace plugin files and refresh cache
- **Critical for OJS 3.5**: This update is REQUIRED for OJS 3.5.0+ installations
- **Backward compatible**: Maintains full compatibility with OJS 3.3 and 3.4
- **Recommended**: Clear OJS cache after upgrade (`php tools/upgrade.php check`)

### From 1.0.0 to 1.0.1
- **No database changes** - Safe to upgrade without data migration
- **No configuration changes** - All existing settings preserved
- **Automatic**: Simply replace plugin files and refresh cache
- **Recommended**: Clear OJS cache after upgrade (`php tools/upgrade.php check`)

### For Fresh Installations
- Installation is now more reliable across all OJS versions
- If automatic installation fails, use the new manual SQL scripts in `install.sql`
- See `INSTALL.md` for detailed installation instructions and troubleshooting

---

## Links
- **Repository**: https://github.com/ssemerikov/reviewerCertificate
- **Issues**: https://github.com/ssemerikov/reviewerCertificate/issues
- **Documentation**: See README.md and INSTALL.md
- **PKP Forum**: Search for "Reviewer Certificate Plugin"
