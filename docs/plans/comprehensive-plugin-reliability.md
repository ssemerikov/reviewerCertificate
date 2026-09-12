# Comprehensive plugin reliability plan

## Global Constraints

Implement the approved investigation fixes without changing existing certificate codes or overwriting AGENTS.md. Preserve PHP 7.3 and OJS 3.3/3.4/3.5 compatibility, public verification URLs, reviewer ownership, and journal isolation. Use OJS database connections, not separate mysqli connections. Write regression tests against production behavior before fixes. The user subsequently authorized GitHub issue replies and releases after verification; do not merge or delete existing local test data. Keep AGENTS.md and local CLAUDE.md edits out of all commits and GitHub uploads.

## Task 1: Shared eligibility and secure paginated issuance

Introduce a shared CertificateService used by download, manual email, automatic issuance, and both batch routes. New issuance requires a completed, nondeclined, noncancelled review and the configured minimum number of eligible reviews in the same journal. Existing certificates are grandfathered against threshold changes, while ownership, journal and completion checks remain mandatory. Make concurrent issuance idempotent and verify persisted IDs.

Require POST, valid CSRF and manager/site-admin authorization on both batch entry points; return 405/403/400 for invalid method/security/input. Validate and deduplicate positive reviewer IDs. Remove raw mysqli and error leakage. Process stable review-ID cursor pages of 100, beyond the old 500 limit. Return generated/skipped/failed, sanitized errors and continuation; any failed item makes status false without losing partial counts. Update settings generation UI to continue pages and display partial failure. Filter the eligible-reviewer picker consistently with journal-local thresholds using portable SQL. Add focused production-path tests for every boundary and both entry points.

## Task 2: Durable automatic and historical notifications

Register reviewerreviewstep3form::execute on all supported OJS versions; re-read the persisted review ID from the form. Stop using invitation dateNotified and nonexistent DAO hooks. Issue eligible certificates and notify once on completion; hook failures must not fail review submission.

Add reviewer_certificate_notifications keyed by certificate ID, with status, attempt count, claim token, attempt time, sent time, and bounded error code. Atomic claims skip sent and active rows; claims older than ten minutes become uncertain and require explicit retry. Pre-upgrade certificates without delivery history are unknown and are sent only through explicit historical catch-up. Use a shared legacy/modern mail adapter with journal contact sender, customized availability templates and localized fallback; mark sent only after transport acceptance. Preserve manual acknowledgement PDF emails.

Add a separate manager historical-notification panel and POST/CSRF-protected notifyBatch action, selected reviewers, missing eligible issuance, one email per certificate, at most ten emails/request, cursor progress and explicit failed/uncertain retry. Ordinary generation must not send email. Add fresh-install and idempotent upgrade schema paths (upgrade.xml), preserve existing records, bump release to 1.10.0.0. Test duplicate hooks, delivery failure, journal sender, concurrency claims, stale claims, explicit catch-up, and repeated migration.

## Task 3: Verification, Unicode PDFs and transactional uploads

Fix verification URL fallback delimiter and reject nonscalar path/query values without warnings or TypeErrors. Retain case-insensitive hex code and current-journal verification.

Render header/body/footer before deciding Unicode fallback so non-Latin literal templates with Latin variables work. Preserve existing layout, empty header and background behavior. Test actual PDF text extraction when available.

Make settings readInputData side-effect free. Validate CSRF, fields and uploaded image before moving files. Handle PHP upload errors, actual size and MIME; use collision-resistant filenames. Persist settings transactionally through OJS, clear cached settings on rollback, remove only a newly staged file after failure, delete previous background only after commit and only within the current journal certificate directory. Surface validation/save failures in AJAX and multipart flows. Add regression tests for failed validation, upload errors, persistence rollback, path safety and successful replacement.

## Task 4: Integration coverage, locales and release readiness

Replace permissive batch browser tests with actual settings-route, database and SMTP assertions. Add tracked isolated OJS/Mailpit environment provisioning with a distinct Compose project and volumes, never execute the existing destructive local setup.sh. Serialize shared-database browser tests and isolate project recipients. Add notification, CSRF, pagination and upload regressions. Test MySQL and PostgreSQL database behavior where available, fresh install and repeated upgrade preserving codes.

Synchronize added locale keys across long XML, PO and short directories using the repository converter. Include Locale in default PHPUnit suites and eliminate risky compatibility assertions. Update relevant documentation/package manifests and CI without modifying AGENTS.md. Run PHPUnit for OJS 3.3/3.4/3.5, syntax checks, locales, dependency audit, available database/browser integrations and report any environment limitations truthfully.
