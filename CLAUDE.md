# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Reviewer Certificate Plugin for Open Journal Systems (OJS). Generates personalized PDF certificates for peer reviewers after completing reviews. Compatible with OJS 3.3.x, 3.4.x, and 3.5.x.

## Branching and Releases

The `main` branch contains a single codebase compatible with all OJS versions. For Plugin Gallery distribution, version-specific branches exist:
- `stable-3_3_0`, `stable-3_4_0`, `stable-3_5_0` — each declares compatibility only with its target OJS version in `version.xml`
- Release packages: `v{VERSION}-3.3`, `v{VERSION}-3.4`, `v{VERSION}-3.5` tags on GitHub
- Plugin Gallery PR: https://github.com/pkp/plugin-gallery/pull/473 (fork: `ssemerikov/plugin-gallery`, branch: `add-reviewer-certificate-plugin`)

## Version

Source of truth: `version.xml` (currently 1.7.0). The `release` field uses 4-part format `X.Y.Z.0`.

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

### PSR-4 Namespace

Root namespace: `APP\plugins\generic\reviewerCertificate` → `./` (defined in `composer.json`). All new classes must use this namespace prefix. Test namespace: `APP\plugins\generic\reviewerCertificate\Tests` → `tests/`.

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
- `controllers/CertificateHandler.php` — HTTP handler: `download()` (reviewer role), `verify()` (public), `myCertificates()` (reviewer), `generateBatch()` (manager role)
- `classes/CertificateGenerator.php` — PDF generation using bundled TCPDF (`vendor/tecnickcom/tcpdf/`)
- `templates/myCertificates.tpl` — "My Certificates" page listing all certificates for a reviewer

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

**Pattern 7: Call `addLocaleData()` in hook handlers that render templates**
When a hook handler fetches a Smarty template with `{translate}` calls, the plugin's locale data must be explicitly loaded. English works as a fallback, but non-English locales require `$this->addLocaleData()` before template rendering.
```php
public function addCertificateButton($hookName, $params) {
    $this->addLocaleData();  // Required for non-English locales
    $templateMgr = $params[0];
    // ... fetch template that uses {translate key="..."}
}
```

**Pattern 8: Unicode font auto-switch in PDF generation**
TCPDF core fonts (Helvetica, Times, Courier) only support Windows-1252. When certificate content contains non-Latin characters (Cyrillic, CJK, Arabic), automatically switch to DejaVu Sans:
```php
// In CertificateGenerator
private function containsNonLatin($text) {
    return preg_match('/[^\x00-\xFF]/u', $text) || preg_match('/[\x{0400}-\x{04FF}]/u', $text);
}

public function generate($reviewAssignment, $context, $template = null) {
    $effectiveFont = $this->fontFamily;
    foreach ([$reviewerName, $journalName, $submissionTitle] as $text) {
        if ($this->containsNonLatin($text)) {
            $effectiveFont = 'dejavusans';
            break;
        }
    }
    $pdf->SetFont($effectiveFont, '', $this->fontSize);
}
```

**Pattern 9: Locale-aware data retrieval for PDFs**
When generating PDFs in non-English locales, fetch localized names and titles:
```php
// In CertificateGenerator
public function setLocale($locale) { $this->locale = $locale; }

private function getEffectiveLocale() {
    if ($this->locale) return $this->locale;
    if (class_exists('PKP\facades\Locale')) return \PKP\facades\Locale::getLocale();  // OJS 3.4+/3.5
    if (class_exists('AppLocale')) return \AppLocale::getLocale();  // OJS 3.3
    return 'en_US';
}

// In SQL queries, prioritize current locale
private function getSubmissionTitleFromDB($submissionId, $locale) {
    return $dao->retrieve(
        'SELECT st.setting_value FROM submission_settings st
         WHERE st.submission_id = ? AND st.setting_name = ? AND st.locale = ?
         ORDER BY CASE WHEN locale = ? THEN 0 ELSE 1 END, locale',
        [$submissionId, 'title', $locale, $locale]
    );
}
```

**Pattern 10: Display review completion date, not certificate issuance date**
The `reviewer_certificates.date_issued` column stores when the DB row was created (identical for batch-generated certificates). For user-facing dates, always use `review_assignments.date_completed`:
```php
// My Certificates page query
SELECT rc.*, ra.date_completed AS review_date_completed
FROM reviewer_certificates rc
LEFT JOIN review_assignments ra ON rc.review_id = ra.review_id
ORDER BY COALESCE(ra.date_completed, rc.date_issued) DESC

// Verification page
$displayDate = $certificate->getDateIssued();
$raRow = $dao->retrieve('SELECT date_completed FROM review_assignments WHERE review_id = ?', [$reviewId]);
if (!empty($raRow['date_completed'])) $displayDate = $raRow['date_completed'];
```

**Pattern 11: URL path fallback for certificate verification**
On some OJS 3.4 configurations, `$args[0]` is not populated from the URL path. Use a fallback chain:
```php
public function verify($args, $request) {
    $code = isset($args[0]) ? $args[0] : $request->getUserVar('code');
    if (!$code) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#/certificate/verify/([A-Fa-f0-9]{8,32})#', $uri, $m)) $code = $m[1];
    }
    if (!$code && !empty($_GET['code'])) $code = $_GET['code'];
    if ($code) $code = strtoupper(trim($code));
}
```

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

**Note**: `phpunit.xml` defaults to `OJS_VERSION=3.4`. Override with env var to test other versions.

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
- `certificate-cyrillic.spec.ts` — Cyrillic/Unicode PDF rendering tests (English + Ukrainian locales)
- `batch-generation.spec.ts` — Batch certificate generation for managers
- `my-certificates.spec.ts` — "My Certificates" page functionality
- `locale-smoke.spec.ts` — Locale translation tests (English + Ukrainian, no `##key##` patterns)

## Release Process

The plugin uses a release script (`release.sh`) to build version-specific packages for OJS Plugin Gallery distribution.

**Why a release script?**
- No OJS version ships TCPDF natively
- OJS ZIP upload has no `composer install` step
- Release archives must include `vendor/tecnickcom/tcpdf/`
- `compat_autoloader.php` must be excluded from OJS 3.4/3.5 packages (causes Issue #68)

**Building releases:**
```bash
./release.sh 1.6.0
# Produces: reviewerCertificate-1.6.0-3_3.tar.gz, -3_4.tar.gz, -3_5.tar.gz
```

**Release packages:**
- `reviewerCertificate-{VERSION}-3_3.tar.gz` — Includes `compat_autoloader.php` + TCPDF
- `reviewerCertificate-{VERSION}-3_4.tar.gz` — TCPDF only (no compat_autoloader)
- `reviewerCertificate-{VERSION}-3_5.tar.gz` — TCPDF only (no compat_autoloader)

Upload to GitHub Releases with tags `v{VERSION}-3.3`, `v{VERSION}-3.4`, `v{VERSION}-3.5`.

## Localization

32 languages in `locale/` directory with dual format support:
- `.xml` files — source of truth for all translations
- `.po` files — **required by all OJS versions** (OJS 3.3.0-22 uses `Gettext\Translations::fromPoFile()` in `LocaleFile::load()`)

**Critical**: Both formats MUST be kept in sync. After editing `.xml` files, regenerate `.po`:
```bash
php temp/convert_xml_to_po.php
```

**Why both formats?** OJS 3.3.0-22's `LocaleFile::load()` reads `.po` files via Gettext, NOT `.xml`. If `.po` files are missing keys that exist in `.xml`, those translations will show as `##key##` at runtime. The `.xml` files remain the source of truth because they're easier to edit and some OJS admin tools reference them.

Current key count: 95 per language (93 for `en/` which lacks 2 error keys). Total tests: 158 PHP + 87 E2E = 245 tests.

Validate translations: `vendor/bin/phpunit tests/Locale/LocaleValidationTest.php`

## CI/CD

GitHub Actions (`.github/workflows/test.yml`) runs on push to `main`, `develop`, `claude/**` and PRs to `main`/`develop`.

**Test matrix**: PHP {7.3, 7.4, 8.0, 8.1, 8.2} × OJS {3.3, 3.4, 3.5} (OJS 3.5 excluded on PHP <8.0). Coverage uploads from PHP 8.1 + OJS 3.4. Code quality job runs `php -l` syntax check.

## Bug Reports

Production bug reports from users (screenshots, PDFs) are stored in `issues/` (untracked). These come from production OJS instances and may inform fixes.
