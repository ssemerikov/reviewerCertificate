# Changelog

All notable changes to the Reviewer Certificate Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
  - Updated development section mentioning comprehensive testing (120 tests)
  - Added PHP version recommendations (8.0+ for OJS 3.5)

### Technical Details
- **Commits**:
  - `16d80c6` - Add manual SQL installation scripts and comprehensive INSTALL guide
  - `292064a` - Fix AJAX "invalid JSON" error in Settings form
  - `ed656a6` - Add automatic OJS 3.3 fallback to database migration
  - `5322a75` - Declare official OJS 3.5 compatibility in plugin metadata
- **Testing**: All existing 120 tests passing across OJS 3.3, 3.4, 3.5
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
- Comprehensive test suite (120 tests)
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
| 1.0.3 | 2025-11-24 | Patch | Font size setting fix - now applies proportionally to all text |
| 1.0.2 | 2025-11-22 | Patch | OJS 3.5 compatibility fix - removed deprecated import() calls |
| 1.0.1 | 2025-11-16 | Patch | Critical bug fixes, OJS 3.5 support, improved installation |
| 1.0.0 | 2025-11-04 | Major | Initial release |

---

## Upgrade Notes

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
