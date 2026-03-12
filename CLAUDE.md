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

### Two-Stage Plugin Loading

The plugin uses a two-stage loading architecture critical for OJS 3.3 compatibility:

```
ReviewerCertificatePlugin.php  (entry point — thin wrapper)
  → compat_autoloader.php      (registers spl_autoload with prepend=true)
  → classes/ReviewerCertificatePluginCore.php  (actual implementation)
  → class_alias() for global namespace (OJS 3.3)
```

**Why**: `compat_autoloader.php` must register BEFORE any namespace resolution occurs. It maps 27+ OJS 3.4+ namespaced classes to their OJS 3.3 global equivalents using `import()`. This allows the same codebase to work across all OJS versions.

### Plugin Structure

```
ReviewerCertificatePlugin.php  # Entry point (namespace: APP\plugins\generic\reviewerCertificate)
compat_autoloader.php          # Namespace compatibility layer (OJS 3.3 ↔ 3.4+)

classes/
  ├── ReviewerCertificatePluginCore.php  # Actual plugin implementation
  │     Hooks: LoadHandler, TemplateManager::display, reviewassignmentdao::_updateobject
  ├── Certificate.php          # Data object (extends PKP\core\DataObject)
  ├── CertificateDAO.php       # Database operations (extends PKP\db\DAO)
  ├── CertificateGenerator.php # PDF generation using bundled TCPDF (lib/tcpdf/)
  ├── form/CertificateSettingsForm.php  # Settings form
  └── migration/ReviewerCertificateInstallMigration.php  # Schema facade + raw SQL fallback

controllers/
  └── CertificateHandler.php   # HTTP handler
      ├── download()     # Certificate PDF (requires reviewer role)
      ├── verify()       # Public certificate verification (no auth)
      └── generateBatch() # Batch generation (requires manager role)
```

### OJS Version Compatibility Patterns

All new code MUST support OJS 3.3, 3.4, and 3.5. Follow these established patterns:

**Pattern 1: class_exists() for API differences**
```php
if (class_exists('PKP\plugins\Hook')) {
    Hook::register(...);       // OJS 3.4+
} else {
    \HookRegistry::register(...);  // OJS 3.3
}
```

**Pattern 2: method_exists() for changed interfaces**
```php
if (method_exists($submission, 'getCurrentPublication')) {
    return $submission->getCurrentPublication()->getLocalizedTitle();  // OJS 3.5
}
return $submission->getLocalizedTitle();  // OJS 3.3/3.4
```

**Pattern 3: Direct SQL when DAOs removed in OJS 3.5**
OJS 3.5 removed `ReviewAssignmentDAO` entirely. Use direct SQL queries via `DAORegistry::getDAO('CertificateDAO')->retrieve()` and create anonymous classes to mimic the removed object interface.

**Pattern 4: Catch `\Throwable` not `\Exception`**
OJS 3.3.0-20+ can throw PHP `Error` types (not just `Exception`) during plugin loading. Always catch `\Throwable`.

### OJS 3.5 Breaking Changes

These removals affect this plugin and require fallback code:
- `ReviewAssignmentDAO` — use direct SQL queries
- `Submission::getLocalizedTitle()` — use `Publication::getLocalizedTitle()` via `getCurrentPublication()`
- `DAO::_getInsertId()` — use `Illuminate\Support\Facades\DB::getPdo()->lastInsertId()`
- `reviewassignmentdao::_updateobject` hook — auto-email on review completion not supported in 3.5

### Database Migration

Two-path strategy in `classes/migration/ReviewerCertificateInstallMigration.php`:
- **OJS 3.4+**: Uses `Illuminate\Support\Facades\Schema` (Laravel)
- **OJS 3.3**: Falls back to raw SQL via DAORegistry
- Edge case: OJS 3.3.0-20+ has Laravel classes present but DB not bootstrapped — the migration catches this and falls back to SQL

### Database Tables

- `reviewer_certificates` — Issued certificate records (unique on review_id and certificate_code)
- `reviewer_certificate_templates` — Per-context template configurations
- `reviewer_certificate_settings` — Localized template settings (composite key: template_id, locale, setting_name)

### Template Variables

Available in certificate body template: `{{$reviewerName}}`, `{{$reviewerFirstName}}`, `{{$reviewerLastName}}`, `{{$journalName}}`, `{{$journalAcronym}}`, `{{$submissionTitle}}`, `{{$reviewDate}}`, `{{$reviewYear}}`, `{{$currentDate}}`, `{{$currentYear}}`, `{{$certificateCode}}`

## Testing

Tests use mocked OJS infrastructure (see `tests/mocks/`). `OJSMockLoader` defines OJS constants and global functions; `DatabaseMock` provides in-memory database operations. Set `OJS_VERSION` env var to test specific versions:

```bash
OJS_VERSION=3.5 vendor/bin/phpunit --testsuite "Compatibility Tests"
```

Test structure:
- `tests/Unit/` — Individual class testing
- `tests/Integration/` — Workflow testing
- `tests/Compatibility/` — OJS 3.3/3.4/3.5 specific tests
- `tests/Security/` — Authorization and input validation
- `tests/Locale/` — Translation validation (82 keys per language)

## Localization

32 languages in `locale/` directory with dual format support:
- `.xml` files for OJS 3.3/3.4
- `.po` files for OJS 3.5+ (required for translations to work)

Both formats must be kept in sync. Use the conversion script: `php temp/convert_xml_to_po.php`

Validate translations: `vendor/bin/phpunit tests/Locale/LocaleValidationTest.php`
