# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Reviewer Certificate Plugin for Open Journal Systems (OJS). Generates personalized PDF certificates for peer reviewers after completing reviews. Compatible with OJS 3.3.x, 3.4.x, and 3.5.x.

## Branching and Releases

The `main` branch contains a single codebase compatible with all OJS versions. For Plugin Gallery distribution, version-specific branches exist:
- `stable-3_3_0`, `stable-3_4_0`, `stable-3_5_0` — each declares compatibility only with its target OJS version in `version.xml`
- Release packages: `v{VERSION}-3.3`, `v{VERSION}-3.4`, `v{VERSION}-3.5` tags on GitHub
- Plugin Gallery PR: https://github.com/pkp/plugin-gallery/pull/473 (fork: `ssemerikov/plugin-gallery`, branch: `add-reviewer-certificate-plugin`)

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

**Critical**: `compat_autoloader.php` must ONLY be included in OJS 3.3 release packages (`stable-3_3_0`). Including it in OJS 3.4+ causes `Cannot declare class` fatal errors because the namespaced classes already exist natively (see Issue #68).

### Key Components

- `ReviewerCertificatePlugin.php` — Entry point (thin wrapper, namespace `APP\plugins\generic\reviewerCertificate`)
- `compat_autoloader.php` — Namespace compatibility layer (OJS 3.3 ↔ 3.4+)
- `classes/ReviewerCertificatePluginCore.php` — Actual implementation. Hooks: `LoadHandler`, `TemplateManager::display`, `reviewassignmentdao::_updateobject`
- `controllers/CertificateHandler.php` — HTTP handler: `download()` (reviewer role), `verify()` (public), `generateBatch()` (manager role)
- `classes/CertificateGenerator.php` — PDF generation using bundled TCPDF (`lib/tcpdf/`)

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

**Pattern 5: Context isolation in SQL queries (multi-journal security)**
All queries on `review_assignments` MUST join `submissions` to filter by `context_id`. Without this, a reviewer on Journal A can access data from Journal B.
```php
$result = $dao->retrieve(
    'SELECT ra.* FROM review_assignments ra
     INNER JOIN submissions s ON ra.submission_id = s.submission_id
     WHERE ra.review_id = ? AND s.context_id = ?',
    array((int) $reviewId, (int) $context->getId())
);
```

**Pattern 6: Strip HTML from submission titles for PDF**
OJS 3.5+ supports HTML in titles (`<em>`, `<strong>`). Always use `strip_tags()` before rendering in PDF context. On Smarty templates, use `|escape` for XSS protection.

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
- `tests/Locale/` — Translation validation (86 keys per language, not included in `composer test:all`)
- `tests/e2e/` — Playwright E2E tests against Docker OJS instances (ports 8033/8034/8035)

### E2E Tests

E2E tests require Docker containers running OJS 3.3, 3.4, and 3.5 (see `ojs-test/docker-compose.yml`):

```bash
# Start OJS test containers
docker compose -f ojs-test/docker-compose.yml up -d

# Run all E2E tests on all OJS versions
npx playwright test --project=ojs33 --project=ojs34 --project=ojs35

# Run a specific E2E test file
npx playwright test plugin-page-smoke --project=ojs34
```

E2E test files:
- `plugin-enable.spec.ts` — Plugin enable/disable and persistence
- `plugin-settings.spec.ts` — Certificate template settings
- `plugin-page-smoke.spec.ts` — Page load smoke tests (no 500 errors after plugin enable, Issue #68)
- `certificate-download.spec.ts` — Reviewer certificate download
- `certificate-verify.spec.ts` — Public certificate verification endpoint
- `certificate-ineligible.spec.ts` — Ineligible reviewer rejection
- `batch-generation.spec.ts` — Batch certificate generation for managers

## Localization

32 languages in `locale/` directory with dual format support:
- `.xml` files — source of truth for all translations
- `.po` files — **required by all OJS versions** (OJS 3.3.0-22 uses `Gettext\Translations::fromPoFile()` in `LocaleFile::load()`)

**Critical**: Both formats MUST be kept in sync. After editing `.xml` files, regenerate `.po`:
```bash
php temp/convert_xml_to_po.php
```

**Why both formats?** OJS 3.3.0-22's `LocaleFile::load()` reads `.po` files via Gettext, NOT `.xml`. If `.po` files are missing keys that exist in `.xml`, those translations will show as `##key##` at runtime. The `.xml` files remain the source of truth because they're easier to edit and some OJS admin tools reference them.

Current key count: 86 per language (84 for `en/` which lacks 2 error keys).

Validate translations: `vendor/bin/phpunit tests/Locale/LocaleValidationTest.php`
