# Changelog

All notable changes to the Reviewer Certificate Plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
| 1.0.1 | 2025-11-16 | Patch | Critical bug fixes, OJS 3.5 support, improved installation |
| 1.0.0 | 2025-11-04 | Major | Initial release |

---

## Upgrade Notes

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
