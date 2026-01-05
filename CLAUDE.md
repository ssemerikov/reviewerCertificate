# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Reviewer Certificate Plugin for Open Journal Systems (OJS). Generates personalized PDF certificates for peer reviewers after completing reviews. Compatible with OJS 3.3.x, 3.4.x, and 3.5.x.

## Development Commands

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run specific test suites
composer test:unit          # Unit tests only
composer test:integration   # Integration tests only
composer test:compatibility # OJS version compatibility tests
composer test:security      # Security tests only

# Run all test suites sequentially
composer test:all

# Run tests with coverage report
composer test:coverage

# Run a single test file
vendor/bin/phpunit tests/Unit/CertificateGeneratorTest.php

# Run a single test method
vendor/bin/phpunit --filter testGeneratePDF tests/Unit/CertificateGeneratorTest.php

# Check PHP syntax across codebase
find . -name "*.php" -not -path "./vendor/*" -not -path "./lib/*" -exec php -l {} \;
```

## Architecture

### Plugin Structure

```
ReviewerCertificatePlugin.php  # Main plugin entry point (namespace: APP\plugins\generic\reviewerCertificate)
  ├── Registers hooks: LoadHandler, TemplateManager::display, reviewassignmentdao::_updateobject
  ├── Manages settings via AJAX modal
  └── Handles batch certificate generation

controllers/
  └── CertificateHandler.php   # HTTP request handler (namespace: APP\plugins\generic\reviewerCertificate\controllers)
      ├── download($reviewId)      # Download certificate PDF (requires reviewer role)
      ├── verify($code)            # Public certificate verification
      └── generateBatch()          # Batch generation (requires manager role)

classes/
  ├── Certificate.php          # Data object (namespace: APP\plugins\generic\reviewerCertificate\classes)
  ├── CertificateDAO.php       # Database operations (extends PKP\db\DAO)
  ├── CertificateGenerator.php # PDF generation using TCPDF
  └── form/CertificateSettingsForm.php  # Settings form (namespace: APP\plugins\generic\reviewerCertificate\classes\form)
```

### Key Design Decisions

1. **OJS Version Compatibility**: Uses `APP\facades\Repo` for user/submission access (OJS 3.4+) with fallback patterns. Avoid deprecated `import()` function - use PHP namespace imports.

2. **TCPDF Bundled**: Located in `lib/tcpdf/`. The generator tries multiple paths for TCPDF to support different OJS installations.

3. **Database Migration**: Uses Laravel migrations for OJS 3.4+ with automatic SQL fallback for OJS 3.3. Manual SQL in `install.sql`.

4. **Hook-Based Integration**: Certificate button injection uses `TemplateManager::display` hook, checking multiple template patterns for different OJS versions.

### Database Tables

- `reviewer_certificates` - Issued certificate records (links to review_id)
- `reviewer_certificate_templates` - Per-context template configurations
- `reviewer_certificate_settings` - Localized template settings

### Template Variables

Available in certificate body template: `{{$reviewerName}}`, `{{$reviewerFirstName}}`, `{{$reviewerLastName}}`, `{{$journalName}}`, `{{$journalAcronym}}`, `{{$submissionTitle}}`, `{{$reviewDate}}`, `{{$reviewYear}}`, `{{$currentDate}}`, `{{$currentYear}}`, `{{$certificateCode}}`

## Testing

Tests use mocked OJS infrastructure (see `tests/mocks/`). Set `OJS_VERSION` env var to test specific versions:

```bash
OJS_VERSION=3.5 vendor/bin/phpunit --testsuite "Compatibility Tests"
```

Test structure:
- `tests/Unit/` - Individual class testing
- `tests/Integration/` - Workflow testing
- `tests/Compatibility/` - OJS 3.3/3.4/3.5 specific tests
- `tests/Security/` - Authorization and input validation
- `tests/Locale/` - Translation validation (82 keys per language)

## Localization

32 languages in `locale/` directory with dual format support:
- `.xml` files for OJS 3.3/3.4
- `.po` files for OJS 3.5+ (required for translations to work)

### Adding New Translations

1. Create new directory (e.g., `locale/fr_FR/`)
2. Copy both `locale/en_US/locale.xml` and `locale/en_US/locale.po`
3. Translate all 82 message keys in both files
4. Update .po header with language code and team
5. Validate with `php vendor/bin/phpunit tests/Locale/LocaleValidationTest.php`

### Converting XML to PO

Use the conversion script: `php temp/convert_xml_to_po.php`

### PO File Format

```
msgid "plugins.generic.reviewerCertificate.displayName"
msgstr "Translated Plugin Name"
```
