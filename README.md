# Reviewer Certificate Plugin for OJS

**Version 1.7.0** | [Changelog](CHANGELOG.md) | OJS 3.3+ / 3.4+ / 3.5+

## Overview

The Reviewer Certificate Plugin enables reviewers to generate and download personalized PDF certificates of recognition after completing peer reviews. This plugin helps journals acknowledge and incentivize quality peer review work.

**Latest Release (v1.7.0)**: 
- **New**: "My Certificates" page for reviewers to browse all their issued certificates
- **Fixed**: Cyrillic/Unicode PDF rendering (no more "??????" garbled text)
- **Fixed**: QR code verification now works with older 12-character codes
- **Fixed**: Correct dates shown on My Certificates and verification pages
- All 87 E2E tests pass across OJS 3.3, 3.4, and 3.5. See [CHANGELOG.md](CHANGELOG.md) for details.

## Author

**Serhiy O. Semerikov**  
Academy of Cognitive and Natural Sciences  
Email: semerikov@gmail.com

## Development

This plugin was developed with the assistance of **Claude Code (Opus 4.6)**, an AI-powered coding assistant by Anthropic. Claude Code was used throughout the development process for:

- **Code Architecture**: Designing the plugin structure and component organization
- **Implementation**: Writing PHP classes, controllers, and data access objects
- **OJS Integration**: Ensuring compatibility with OJS 3.3.x, 3.4.x, and 3.5.x APIs
- **Database Design**: Creating the migration system and schema with automatic fallback for legacy versions
- **Testing & Debugging**: Comprehensive test suite with 158 PHP tests + 87 E2E tests across all OJS versions
- **Documentation**: Creating comprehensive user and technical documentation

The iterative development approach with Claude Code enabled rapid prototyping, thorough testing across OJS versions, and production-ready code quality.

## Features

- **Automated Certificate Generation**: Certificates are automatically available when reviewers complete their reviews
- **My Certificates Page**: Reviewers can browse all their issued certificates in one place with submission titles, dates, and download links
- **Customizable Templates**: Each journal can design unique certificate templates with custom backgrounds, fonts, and colors
- **Dynamic Content**: Insert reviewer names, journal names, submission titles, and dates using template variables
- **Unicode Font Support**: Automatic detection of non-Latin scripts (Cyrillic, CJK, Arabic) with seamless font switching
- **Eligibility Criteria**: Set minimum review requirements before certificates become available
- **QR Code Verification**: Include QR codes for certificate authenticity verification
- **Download Tracking**: Track certificate downloads and usage statistics
- **Multi-language Support**: Full internationalization with professional native translations
  - 32 languages with complete coverage (95 message keys each)
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

1. Download `reviewerCertificate-{VERSION}-3_X.tar.gz` from [Releases](https://github.com/ssemerikov/reviewerCertificate/releases)
   - Use `-3_3.tar.gz` for OJS 3.3.x
   - Use `-3_4.tar.gz` for OJS 3.4.x
   - Use `-3_5.tar.gz` for OJS 3.5.x

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

2. Install dependencies:
   ```bash
   cd reviewerCertificate
   composer install --no-dev
   ```

3. Set permissions:
   ```bash
   chmod -R 755 .
   ```

4. Enable in OJS:
   - Log in as Administrator
   - Go to **Settings → Website → Plugins**
   - Find "Reviewer Certificate Plugin"
   - Click **Enable**
   - Database tables will be created automatically

5. Configure and use:
   - Click **Settings** to customize certificate templates
   - Click **Preview Certificate** to test your design

**Note:** The plugin includes TCPDF library in release archives, so no additional dependencies need to be installed when using the tar.gz packages.

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
  - ⚠️ **Note**: Helvetica, Times, and Courier only support Latin scripts. If your certificate contains Cyrillic, CJK, Arabic, or other non-Latin characters, the plugin will automatically switch to DejaVu Sans.
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

The Reviewer Certificate Plugin is fully internationalized and available in 32 languages.

### Supported Languages

| Language | Locale Code | Native Name | Status |
|----------|-------------|-------------|--------|
| English (US) | `en_US` | English | ✅ Complete |
| English | `en` | English | ✅ Complete |
| Ukrainian | `uk_UA` | Українська | ✅ Complete |
| Russian | `ru_RU` | Русский | ✅ Complete |
| Spanish | `es_ES` | Español | ✅ Complete |
| Portuguese (BR) | `pt_BR` | Português (Brasil) | ✅ Complete |
| French | `fr_FR` | Français | ✅ Complete |
| German | `de_DE` | Deutsch | ✅ Complete |
| Italian | `it_IT` | Italiano | ✅ Complete |
| Turkish | `tr_TR` | Türkçe | ✅ Complete |
| Polish | `pl_PL` | Polski | ✅ Complete |
| Indonesian | `id_ID` | Bahasa Indonesia | ✅ Complete |
| Dutch | `nl_NL` | Nederlands | ✅ Complete |
| Czech | `cs_CZ` | Čeština | ✅ Complete |
| Catalan | `ca_ES` | Català | ✅ Complete |
| Norwegian (Bokmål) | `nb_NO` | Norsk Bokmål | ✅ Complete |
| Swedish | `sv_SE` | Svenska | ✅ Complete |
| Croatian | `hr_HR` | Hrvatski | ✅ Complete |
| Finnish | `fi_FI` | Suomi | ✅ Complete |
| Romanian | `ro_RO` | Română | ✅ Complete |
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

### Language Features

- **Automatic Detection**: The plugin automatically uses the language selected in your OJS installation
- **UTF-8 Support**: Full support for Cyrillic, Latin, CJK, Arabic, and other character sets
- **Certificate Content**: All interface text is translated; certificate templates can be customized in any language
- **Template Variables**: Template variables like `{{$reviewerName}}` work in all languages

## Usage

### For Reviewers

1. Complete a peer review assignment
2. Submit your review
3. If eligible, a certificate download button will appear in your reviewer dashboard
4. Click **Download Certificate** to generate and download your PDF certificate
5. Click **View All My Certificates** to browse all your issued certificates
6. The certificate includes a unique verification code for authenticity

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

## Troubleshooting

### Certificates Not Appearing

- Verify the plugin is enabled
- Check that reviews are marked as completed
- Confirm minimum review requirements are met
- Check file permissions on the files directory

### PDF Generation Errors

- Ensure TCPDF library is properly installed (bundled in release archives)
- Verify PHP memory limit (recommended: 256MB)
- Check PHP error logs for specific issues
- Ensure GD or Imagick extension is enabled

### Cyrillic/Unicode Characters Show as "??????"

- This is now handled automatically — the plugin detects non-Latin characters and switches to DejaVu Sans
- If you still see garbled text, ensure your database uses UTF-8 encoding (`utf8mb4`)
- Verify that reviewer names and submission titles are stored correctly in the database

### QR Code Verification Not Working

- Ensure the certificate code in the URL is 8-32 hexadecimal characters
- Older certificates may have 12-character codes (still supported)
- Check that the certificate exists in the `reviewer_certificates` table

## API Endpoints

The plugin provides these API endpoints:

| Endpoint | Description | Access |
|----------|-------------|--------|
| `GET /certificate/download/{reviewId}` | Download certificate | Reviewer (own) |
| `GET /certificate/myCertificates` | Browse all my certificates | Reviewer |
| `GET /certificate/verify/{certificateCode}` | Verify certificate | Public |
| `GET /certificate/preview` | Preview certificate template | Manager |
| `POST /certificate/generateBatch` | Generate batch certificates | Manager |

## Security Considerations

- Certificates include unique verification codes
- Access control ensures reviewers can only access their own certificates
- QR codes link to verification endpoints
- Download tracking for audit purposes
- CSRF protection on all forms
- File upload validation and sanitization
- Context isolation prevents cross-journal data access

## Database Schema

The plugin creates these tables:

- `reviewer_certificate_templates` — Template configurations
- `reviewer_certificates` — Issued certificates
- `reviewer_certificate_settings` — Localized settings

## Compatibility

| OJS Version | PHP Version | Status |
|-------------|-------------|--------|
| 3.3.x | 7.3 - 8.2 | ✅ Fully tested |
| 3.4.x | 7.4 - 8.2 | ✅ Fully tested |
| 3.5.x | 8.0 - 8.2 | ✅ Fully tested |

**Test Coverage**: 158 PHP unit tests + 87 E2E tests = 245 total tests

## Support

For issues, questions, or feature requests:

1. Check the [CHANGELOG.md](CHANGELOG.md) for recent fixes
2. Search existing [Issues](https://github.com/ssemerikov/reviewerCertificate/issues)
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

Copyright (c) 2025-2026 Serhiy O. Semerikov, Academy of Cognitive and Natural Sciences

## Acknowledgments

We extend our sincere gratitude to the following contributors who helped improve this plugin:

- **Dr. Olha Pinchuk** (ITLT Journal, Ukraine) — For extensive production testing on OJS 3.4.0.8, reporting critical bugs (Cyrillic PDF rendering, QR verification, date display), and inspiring the "My Certificates" page feature. Your thorough testing and feedback were invaluable for v1.7.0.

- **Dr. Uğur Koçak** ([@drugurkocak](https://github.com/drugurkocak)) — For extensive testing across OJS 3.5 and detailed bug reports that led to fixes in versions 1.0.7 through 1.1.2.

- **Pedro Felipe Rocha** — For Brazilian Portuguese translation and feedback on OJS 3.5 locale requirements.

- **Dr. Pavlo Nechypurenko** — For reporting the font size configuration issue fixed in v1.0.3.

Thank you to everyone in the PKP Community Forum who provided feedback and helped test the plugin!

## Credits

**Author**: Serhiy O. Semerikov (Academy of Cognitive and Natural Sciences)  
**Contact**: semerikov@gmail.com

Developed for the Open Journal Systems community to support and recognize peer reviewers' contributions to scholarly publishing.

**Development Tools**: Built with Claude Code (Opus 4.6) by Anthropic

## Additional Resources

- [OJS Documentation](https://docs.pkp.sfu.ca/learning-ojs/)
- [PKP Community Forum](https://forum.pkp.sfu.ca/)
- [TCPDF Documentation](https://tcpdf.org/)

---

**Note**: Always backup your database and files before installing new plugins.