# Reviewer Certificate Plugin for OJS

**Version 1.1.5** | [Changelog](CHANGELOG.md) | OJS 3.3+ / 3.4+ / 3.5+

## Overview

The Reviewer Certificate Plugin enables reviewers to generate and download personalized PDF certificates of recognition after completing peer reviews. This plugin helps journals acknowledge and incentivize quality peer review work.

**Latest Release (v1.1.5)**: Fixed OJS 3.3 compatibility - plugin no longer causes infinite loading on plugins page. See [CHANGELOG.md](CHANGELOG.md) for details.

## Author

**Serhiy O. Semerikov**
Academy of Cognitive and Natural Sciences
Email: semerikov@gmail.com

## Development

This plugin was developed with the assistance of **Claude Code (Sonnet 4.5)**, an AI-powered coding assistant by Anthropic. Claude Code was used throughout the development process for:

- **Code Architecture**: Designing the plugin structure and component organization
- **Implementation**: Writing PHP classes, controllers, and data access objects
- **OJS Integration**: Ensuring compatibility with OJS 3.3.x, 3.4.x, and 3.5.x APIs
- **Database Design**: Creating the migration system and schema with automatic fallback for legacy versions
- **Testing & Debugging**: Comprehensive test suite with 121 tests across all OJS versions
- **Documentation**: Creating comprehensive user and technical documentation
- **Code Review**: Analyzing code quality and identifying potential improvements

The iterative development approach with Claude Code enabled rapid prototyping, thorough testing across OJS versions (3.3, 3.4, 3.5), and production-ready code quality.

## Features

- **Automated Certificate Generation**: Certificates are automatically available when reviewers complete their reviews
- **Customizable Templates**: Each journal can design unique certificate templates with custom backgrounds, fonts, and colors
- **Dynamic Content**: Insert reviewer names, journal names, submission titles, and dates using template variables
- **Eligibility Criteria**: Set minimum review requirements before certificates become available
- **QR Code Verification**: Include QR codes for certificate authenticity verification
- **Download Tracking**: Track certificate downloads and usage statistics
- **Multi-language Support**: Full internationalization with professional native translations
  - 32 languages with complete coverage (82 message keys each)
  - Includes RTL support (Arabic, Persian, Hebrew), CJK languages (Chinese, Japanese, Korean), and Cyrillic scripts
  - Dual format support: `.xml` (OJS 3.3/3.4) and `.po` (OJS 3.5) locale files
  - Automatic language detection from OJS settings
  - All translations validated with comprehensive test suite
- **Batch Generation**: Generate certificates for multiple reviewers at once

## Requirements

- **OJS Version**: 3.3.x, 3.4.x, or 3.5.x
- **PHP**: 7.3 or higher (PHP 8.0+ recommended for OJS 3.5)
- **Required PHP Extensions**:
  - GD or Imagick (for image processing)
  - mbstring
  - zip
- **TCPDF Library**: ✅ Bundled with plugin (v6.10.0) - no additional installation required!
- **Memory**: 128MB minimum PHP memory limit (256MB recommended for large background images)

### Version Compatibility

| OJS Version | Support Status | Notes |
|-------------|----------------|-------|
| 3.3.x | ✅ Fully Supported | Automatic migration with SQL fallback |
| 3.4.x | ✅ Fully Supported | Modern Laravel migration |
| 3.5.x | ✅ Fully Supported | Latest features, PHP 8+ optimized |

## Installation

### Quick Install via OJS Admin (Recommended)

1. Download `reviewerCertificate.tar.gz` from [Releases](https://github.com/ssemerikov/reviewerCertificate/releases)

2. In OJS, go to **Settings → Website → Plugins**

3. Click **Upload A New Plugin** and select the tar.gz file

4. Click **Enable** on the Reviewer Certificate Plugin

5. Click **Settings** to customize certificate templates

**That's it!** No command line needed.

### Alternative: Git Clone

1. Clone or download the plugin:
   ```bash
   cd /path/to/ojs/plugins/generic/
   git clone https://github.com/ssemerikov/reviewerCertificate.git
   ```

2. Set permissions:
   ```bash
   chmod -R 755 reviewerCertificate/
   ```

3. Enable in OJS:
   - Log in as Administrator
   - Go to **Settings → Website → Plugins**
   - Find "Reviewer Certificate Plugin"
   - Click **Enable**
   - Database tables will be created automatically

4. Configure and use:
   - Click **Settings** to customize certificate templates
   - Click **Preview Certificate** to test your design

**Note:** The plugin includes TCPDF library, so no additional dependencies need to be installed.

### Manual Installation (If Automatic Fails)

If you encounter database errors like "Table 'reviewer_certificates' doesn't exist":

1. Download and install plugin files (see above)

2. **Create database tables manually:**
   ```bash
   cd /path/to/ojs/plugins/generic/reviewerCertificate/
   mysql -u [username] -p [database_name] < install.sql
   ```

   Or use phpMyAdmin to run the SQL from `install.sql`

3. Enable the plugin in OJS admin interface

📖 **See [INSTALL.md](INSTALL.md) for detailed installation instructions and troubleshooting.**

## Configuration

### Initial Setup

1. Navigate to: **Settings > Website > Plugins**

2. Find **Reviewer Certificate Plugin** and click **Settings**

3. Configure the following options:

#### Template Settings

- **Background Image**: Upload a custom background image (2100x2970 px recommended)
- **Header Text**: The main heading (e.g., "Certificate of Recognition")
- **Body Template**: The main certificate content with template variables
- **Footer Text**: Optional footer text

#### Appearance Settings

- **Font Family**: Choose from Helvetica, Times New Roman, Courier, or DejaVu Sans
- **Font Size**: Set the base font size (default: 12pt)
- **Text Color**: Set RGB values for text color (0-255 for each component)

#### Eligibility Criteria

- **Minimum Reviews**: Set how many completed reviews are required (default: 1)
- **Include QR Code**: Enable/disable QR code for verification

### Template Variables

Use these variables in your templates to insert dynamic content:

| Variable | Description |
|----------|-------------|
| `{{$reviewerName}}` | Full name of the reviewer |
| `{{$reviewerFirstName}}` | Reviewer's first name |
| `{{$reviewerLastName}}` | Reviewer's last name |
| `{{$journalName}}` | Full journal name |
| `{{$journalAcronym}}` | Journal acronym |
| `{{$submissionTitle}}` | Title of the reviewed manuscript |
| `{{$reviewDate}}` | Date review was completed |
| `{{$reviewYear}}` | Year of review completion |
| `{{$currentDate}}` | Current date |
| `{{$currentYear}}` | Current year |
| `{{$certificateCode}}` | Unique certificate verification code |

### Example Certificate Template

```
Certificate of Recognition

This certificate is awarded to

{{$reviewerName}}

In recognition of their valuable contribution as a peer reviewer for

{{$journalName}}

Review completed on {{$reviewDate}}

Manuscript: {{$submissionTitle}}

We deeply appreciate your expertise and dedication to advancing scholarly communication.
```

## Language Support

The Reviewer Certificate Plugin is fully internationalized and available in multiple languages.

### Locale File Formats

The plugin provides both `.xml` and `.po` locale files for maximum compatibility:

| Format | OJS Version | Location |
|--------|-------------|----------|
| `.xml` | 3.3, 3.4 | `locale/{lang}/locale.xml` |
| `.po` | 3.5+ | `locale/{lang}/locale.po` |

**Note:** OJS 3.5 requires `.po` files for translations to work correctly.

### Supported Languages

| Language | Locale Code | Native Name | Status |
|----------|-------------|-------------|--------|
| English (US) | `en_US` | English | ✅ Complete |
| English | `en` | English | ✅ Complete |
| Ukrainian | `uk_UA` | Українська | ✅ Complete |
| Russian | `ru_RU` | Русский | ✅ Complete |
| Spanish | `es_ES` | Español | ✅ Complete |
| Portuguese (BR) | `pt_BR` | Português (Brasil) | ✅ Complete |
| French | `fr_FR` | Français (France) | ✅ Complete |
| German | `de_DE` | Deutsch (Deutschland) | ✅ Complete |
| Italian | `it_IT` | Italiano (Italia) | ✅ Complete |
| Turkish | `tr_TR` | Türkçe (Türkiye) | ✅ Complete |
| Polish | `pl_PL` | Polski (Polska) | ✅ Complete |
| Indonesian | `id_ID` | Bahasa Indonesia (Indonesia) | ✅ Complete |
| Dutch | `nl_NL` | Nederlands (Nederland) | ✅ Complete |
| Czech | `cs_CZ` | Čeština (Česká republika) | ✅ Complete |
| Catalan | `ca_ES` | Català (Catalunya) | ✅ Complete |
| Norwegian (Bokmål) | `nb_NO` | Norsk Bokmål (Norge) | ✅ Complete |
| Swedish | `sv_SE` | Svenska (Sverige) | ✅ Complete |
| Croatian | `hr_HR` | Hrvatski (Hrvatska) | ✅ Complete |
| Finnish | `fi_FI` | Suomi (Suomi) | ✅ Complete |
| Romanian | `ro_RO` | Română (România) | ✅ Complete |
| Chinese (Simplified) | `zh_CN` | 简体中文 | ✅ Complete |
| Arabic | `ar_AR` | العربية | ✅ Complete |
| Japanese | `ja_JP` | 日本語 | ✅ Complete |
| Korean | `ko_KR` | 한국어 | ✅ Complete |
| Persian/Farsi | `fa_IR` | فارسی | ✅ Complete |
| Greek | `el_GR` | Ελληνικά | ✅ Complete |
| Hebrew | `he_IL` | עברית | ✅ Complete |
| Hungarian | `hu_HU` | Magyar | ✅ Complete |
| Lithuanian | `lt_LT` | Lietuvių | ✅ Complete |
| Slovak | `sk_SK` | Slovenčina | ✅ Complete |
| Slovenian | `sl_SI` | Slovenščina | ✅ Complete |
| Bulgarian | `bg_BG` | Български | ✅ Complete |

### Global Coverage

With 32 languages, the plugin now serves **approximately 95% of the global OJS user base** across:

- **Western Europe (7)**: English, French, German, Italian, Spanish, Dutch, Catalan
- **Nordic Region (3)**: Norwegian (Bokmål), Swedish, Finnish
- **Central Europe (5)**: Czech, Hungarian, Slovak, Slovenian, Lithuanian
- **Southeastern Europe (4)**: Romanian, Croatian, Bulgarian, Greek
- **Eastern Europe & Eurasia (4)**: Russian, Ukrainian, Polish, Turkish
- **Latin America (2)**: Spanish, Portuguese (Brazilian)
- **Middle East & North Africa (4)**: Arabic, Persian/Farsi, Hebrew, Turkish
- **East Asia (3)**: Chinese (Simplified), Japanese, Korean
- **Southeast Asia (1)**: Indonesian

**Language Families Represented:**
- Indo-European (20 languages): Romance, Germanic, Slavic, Baltic, Hellenic, Indo-Iranian branches
- Uralic (2 languages): Finnish, Hungarian
- Turkic (1 language): Turkish
- Semitic (2 languages): Arabic, Hebrew
- CJK (3 languages): Chinese, Japanese, Korean

**Special Script Support:**
- **RTL Languages**: Arabic, Persian, Hebrew (right-to-left text direction)
- **CJK Scripts**: Chinese Han, Japanese Kanji/Kana, Korean Hangul
- **Cyrillic**: Russian, Ukrainian, Bulgarian

All translations feature scholarly terminology appropriate for academic publishing contexts and native speaker quality.

### Language Features

- **Automatic Detection**: The plugin automatically uses the language selected in your OJS installation
- **UTF-8 Support**: Full support for Cyrillic, Latin, and other character sets
- **Certificate Content**: All interface text is translated, but certificate templates can be customized in any language
- **Template Variables**: Template variables like `{{$reviewerName}}` work in all languages

### Contributing Translations

We welcome community contributions for additional languages! To contribute:

1. Create a new directory for your language (e.g., `locale/fr_FR/`)
2. Copy both `locale/en_US/locale.xml` and `locale/en_US/locale.po` to your new directory
3. Translate all message strings while preserving template variables (e.g., `{{$reviewerName}}`)
4. Update the .po file header with your language code and language team
5. Test your translation with the locale validation tests: `php vendor/bin/phpunit tests/Locale/LocaleValidationTest.php`
6. Submit a pull request

**Note:** Both `.xml` and `.po` files are required for full OJS compatibility.

**All priority languages are now complete!** Community contributions for additional regional variants are welcome.

## Usage

### For Reviewers

1. Complete a peer review assignment
2. Submit your review
3. If eligible, a certificate download button will appear in your reviewer dashboard
4. Click **Download Certificate** to generate and download your PDF certificate
5. The certificate includes a unique verification code for authenticity

### For Journal Managers

#### Viewing Certificate Statistics

1. Navigate to: **Settings > Website > Plugins > Reviewer Certificate Plugin**
2. View statistics on issued certificates and downloads

#### Batch Certificate Generation

1. Navigate to the plugin settings
2. Select multiple reviewers
3. Click **Generate Batch Certificates**
4. Certificates will be generated for all eligible completed reviews

#### Certificate Verification

To verify a certificate's authenticity:

1. Navigate to: `[your-journal-url]/certificate/verify/[certificate-code]`
2. The system will display certificate details if valid

## Customization

### Custom Background Design

Create a professional certificate background:

1. **Dimensions**: 210mm x 297mm (A4)
2. **Resolution**: 300 DPI
3. **Format**: JPG or PNG
4. **Margins**: Leave at least 15mm on all sides for text
5. **Design Elements**: Add borders, seals, logos, or watermarks

### Styling Certificates

Modify the appearance using the settings form:

- Adjust font family and size
- Change text colors using RGB values
- Position elements using the layout settings
- Preview changes before saving

### Advanced Customization

For developers who want to customize further:

1. **Modify CertificateGenerator.php**: Customize PDF generation logic
2. **Edit certificate.css**: Change button and interface styling
3. **Update certificate.js**: Add custom JavaScript functionality
4. **Extend locale files**: Add translations for additional languages

## Troubleshooting

### Certificates Not Appearing

- Verify the plugin is enabled
- Check that reviews are marked as completed
- Confirm minimum review requirements are met
- Check file permissions on the files directory

### PDF Generation Errors

- Ensure TCPDF library is properly installed
- Verify PHP memory limit (recommended: 256MB)
- Check PHP error logs for specific issues
- Ensure GD or Imagick extension is enabled

### Background Image Issues

- Verify image format (JPG or PNG only)
- Check image file size (keep under 5MB)
- Ensure correct file permissions
- Use recommended dimensions (2100x2970 pixels)

### Download Permission Errors

- Verify reviewer is logged in
- Check that reviewer owns the review assignment
- Ensure review is marked as completed
- Check server file permissions

## API Endpoints

The plugin provides these API endpoints:

- `GET /certificate/download/{reviewId}` - Download certificate
- `GET /certificate/verify/{certificateCode}` - Verify certificate
- `GET /certificate/preview` - Preview certificate template
- `POST /certificate/generateBatch` - Generate batch certificates

## Security Considerations

- Certificates include unique verification codes
- Access control ensures reviewers can only access their own certificates
- QR codes link to verification endpoints
- Download tracking for audit purposes
- CSRF protection on all forms
- File upload validation and sanitization

## Database Schema

The plugin creates these tables:

- `reviewer_certificate_templates` - Template configurations
- `reviewer_certificates` - Issued certificates
- `reviewer_certificate_settings` - Localized settings

## Performance

- PDF generation is optimized for quick response
- Background images are cached
- Database queries are indexed
- Supports high-volume certificate generation

## Compatibility

- **OJS 3.3.x**: ✅ Fully compatible with automatic SQL fallback
- **OJS 3.4.x**: ✅ Fully compatible with modern Laravel migration
- **OJS 3.5.x**: ✅ Fully compatible with latest features
- **PHP 7.3+**: Required (minimum)
- **PHP 8.0+**: Recommended (especially for OJS 3.5)
- **PHP 8.2+**: Fully tested and supported

**Tested on:** PHP 7.3, 7.4, 8.0, 8.1, 8.2 across all OJS versions

## Support

For issues, questions, or feature requests:

1. Check the [documentation](#)
2. Search existing [issues](#)
3. Create a new issue with:
   - OJS version
   - PHP version
   - Error messages
   - Steps to reproduce

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This plugin is licensed under the GNU General Public License v3.0.

Copyright (c) 2025 Serhiy O. Semerikov, Academy of Cognitive and Natural Sciences

## Changelog

For detailed version history and changes, see [CHANGELOG.md](CHANGELOG.md).

### Recent Releases

**Version 1.1.2** (2026-01-06)
- **Fixed**: Critical OJS 3.4 backward compatibility issues after v1.1.1
- **Fixed**: Missing `class_alias()` calls for OJS 3.3 fallback patterns
- **Fixed**: Unqualified global PHP class references (`mysqli`, `stdClass`, `TCPDF`, etc.)
- **Fixed**: Missing `use` statements for OJS core classes
- Fully tested on OJS 3.3, 3.4, and 3.5

**Version 1.1.1** (2026-01-05)
- **Fixed**: Critical `_getInsertId()` error in OJS 3.5 - certificates now generate correctly
- **Fixed**: Missing `use` statements causing class loading errors (Application, CertificateSettingsForm)
- **Fixed**: Tarball extraction - plugin now extracts to correct `reviewerCertificate/` directory
- Addresses issues reported by @drugurkocak (GitHub Issue #57)

**Version 1.1.0** (2026-01-05)
- **BREAKING**: Full OJS 3.5 compatibility update with PSR-4 namespaces
- **Changed**: Renamed all `.inc.php` files to `.php` (OJS 3.5 requirement)
- **Changed**: Added proper PHP namespaces to all plugin classes
- **Changed**: Replaced deprecated `import()` calls with `use` statements
- **Changed**: Replaced removed `fatalError()` calls with PHP exceptions
- **Fixed**: OJS 3.5 handler registration using direct assignment pattern
- **Fixed**: URL path parameter type errors (must be arrays in OJS 3.5)
- Fully backward compatible with OJS 3.3 and 3.4
- Resolves all OJS 3.5 compatibility issues (Issue #57)

**Version 1.0.7** (2025-01-04)
- **Fixed**: Critical OJS 3.5 URL parameter type error preventing certificate button display
- **Fixed**: Critical OJS 3.5 handler registration error (`HANDLER_CLASS` no longer supported)
- **Fixed**: `$request->url()` calls now correctly pass path as array
- Fully compatible with OJS 3.5 handler system (Issue #57)
- Addresses issues reported by @drugurkocak

**Version 1.0.6** (2025-01-04)
- **Added**: 12 new language translations expanding global coverage to ~95%
  - **Tier 3 Priority**: Chinese (zh_CN), Arabic (ar_AR), Japanese (ja_JP), Korean (ko_KR), Persian (fa_IR), Greek (el_GR), Hebrew (he_IL)
  - **Medium Priority**: Hungarian (hu_HU), Lithuanian (lt_LT), Slovak (sk_SK), Slovenian (sl_SI), Bulgarian (bg_BG)
- **Added**: RTL (right-to-left) language support for Arabic, Persian, and Hebrew
- **Added**: CJK (Chinese, Japanese, Korean) language support
- **Added**: Additional Cyrillic script support (Bulgarian)

**Version 1.0.5** (2025-01-03)
- **Fixed**: Date format display on certificate verification page showing `%B %e, %Y` instead of formatted date
- **Fixed**: PHP 8.1+ compatibility - replaced deprecated `strftime()` with `date()` function
- **Fixed**: Missing return statement after error in download handler causing potential memory issues
- Addresses issues reported by Olha P. Pinchuk

**Version 1.0.4** (2025-01-03)
- **Added**: `.po` locale files for all 20 languages (OJS 3.5 compatibility)
- **Added**: Brazilian Portuguese translation (community contribution by Pedro Felipe Rocha)
- **Added**: Simplified installation via tar.gz upload
- **Improved**: Documentation with user-friendly installation guide
- Addresses community feedback on PKP Community Forum

**Version 1.0.3** (2025-11-24)
- **Fixed**: Font size setting not being applied to PDF content
- **Fixed**: OJS 3.5 redirect() signature compatibility
- **Fixed**: ReviewAssignmentDAO deprecation in OJS 3.5
- **Added**: OJS 3.3 namespace compatibility (conditional class loading)

**Version 1.0.2** (2025-11-22)
- **Fixed**: Critical OJS 3.5 compatibility issue - "Call to undefined function import()" errors
- **Changed**: Replaced all deprecated `import()` calls with modern PHP namespace imports
- **Improved**: Full compatibility with OJS 3.3, 3.4, and 3.5
- Addresses installation issues reported by Dr. Uğur Koçak on PKP Community Forum

**Version 1.0.1** (2025-11-16)
- **Fixed**: Critical AJAX settings form error ("Failed Ajax request or invalid JSON returned")
- **Fixed**: Database migration failures in OJS 3.3 (tables not being created)
- **Added**: Automatic migration fallback for OJS 3.3 compatibility
- **Added**: Manual SQL installation scripts (install.sql, uninstall.sql)
- **Added**: Comprehensive INSTALL.md with troubleshooting guide
- **Added**: Official OJS 3.5 compatibility declaration
- **Improved**: Error handling and null checks throughout
- Addresses multiple PKP forum community issues

**Version 1.0.0** (2025-11-04)
- Initial release
- Automated certificate generation
- Customizable templates
- QR code verification
- Multi-language support
- Batch generation capability
- Full test suite (121 tests)

## Acknowledgments

We extend our sincere gratitude to the following contributors who helped improve this plugin:

- **Dr. Uğur Koçak** ([@drugurkocak](https://github.com/drugurkocak)) - For extensive testing across OJS 3.5 and detailed bug reports that led to fixes in versions 1.0.7, 1.0.8, 1.1.0, 1.1.1, and 1.1.2. Your dedication to thorough testing was invaluable.
- **Pedro Felipe Rocha** - For Brazilian Portuguese translation and feedback on OJS 3.5 locale requirements
- **Olha P. Pinchuk** - For reporting date format and memory issues fixed in v1.0.5
- **Dr. Pavlo Nechypurenko** - For reporting the font size configuration issue fixed in v1.0.3

Thank you to everyone in the PKP Community Forum who provided feedback and helped test the plugin!

## Credits

**Author**: Serhiy O. Semerikov (Academy of Cognitive and Natural Sciences)
**Contact**: semerikov@gmail.com

Developed for the Open Journal Systems community to support and recognize peer reviewers' contributions to scholarly publishing.

**Development Tools**: Built with Claude Code (Opus 4.5) by Anthropic

## Additional Resources

- [OJS Documentation](https://docs.pkp.sfu.ca/learning-ojs/)
- [PKP Community Forum](https://forum.pkp.sfu.ca/)
- [TCPDF Documentation](https://tcpdf.org/)

---

**Note**: Always backup your database and files before installing new plugins.
