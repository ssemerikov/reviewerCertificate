# Reviewer certificate 1.10 verification

Local verification completed 2026-09-12; the implementation is integrated into
`main`. This record distinguishes local coverage from remote CI and its limits.

## Automated checks

| Check | Result |
| --- | --- |
| PHPUnit, OJS 3.3 target | 244 tests; 8,779 assertions; 39 skips |
| PHPUnit, OJS 3.4 target | 244 tests; 8,780 assertions; 37 skips |
| PHPUnit, OJS 3.5 target | 244 tests; 8,784 assertions; 35 skips |
| Strict browser suite | All nine tests passed together; 2.2 minutes |
| JavaScript regression | Passed |
| Release contents / PHP dependency targets | All three archives passed |
| Extracted PDF runtime | All three passed, including fonts/styles, Unicode and QR |

PHPUnit ran serially on host PHP 8.1 with version-specific OJS mocks. Skips
include tests for other OJS versions and five GD-dependent tests. GD downscaling
was separately verified in the OJS 3.5 container. Actual OJS integrations used
3.3.0-22/PHP 8.2, 3.4.0-10/PHP 8.2 and 3.5.0-3/PHP 8.3.

The browser suite exercises both batch routes' POST/CSRF/role checks, persisted
issuance, real Mailpit delivery, repeat-send prevention, threshold-raised historical
selection, multipart validation and restrictive file permissions. Earlier runs
timed out under host memory/I/O pressure; the final serial run passed without retries
using a 120-second per-test budget.

## Database and upgrade checks

All three frameworks passed MySQL and PostgreSQL checks for fresh/repeated
migrations, concurrent issuance and notification initialization within outer
transactions, claim exclusion, foreign-key cascades, preserving orphaned legacy
certificates, normalizing absent templates and adding deferred constraints after
parent reconciliation. Each race uses a separate live database connection and a
randomly named disposable schema/database.

PR #75's SELECT-alias/HAVING fix is incorporated equivalently in the newer picker
query. `check-picker-sql.php` exercises the actual production query on both engines,
covering eligibility, journal scope, missing-certificate counts and historical
selection after a threshold increase. Reintroducing the alias reproduces PostgreSQL
SQLSTATE 42703; restoring aggregate expressions passes. The test captures real
database rows before user-name hydration, which is covered by MySQL UI checks.

All three OJS installations also passed real core email-manifest installation,
customized-mail preservation, historical selection, ambient-transaction delivery
deferral and uncertain transport-outcome checks. The warm-upgrade regression
keeps the old migration class loaded while running the real installer and verifies
that only superseded schema/email callbacks are retired.

## Review and limitations

The remote PHP matrix passed all 12 supported PHP/OJS combinations, including
PHP 7.3, with GD and coverage enabled, plus the code-quality job
([CI run](https://github.com/ssemerikov/reviewerCertificate/actions/runs/34685051037)).
Initial CI failures identified two test-harness assumptions: newer mail metadata
must not be parsed by PHP 7.3, and CLI diagnostics must not send HTTP output before
status assertions. Both are corrected; the matrix no longer cancels other targets
when one fails.

Clean OJS integration also exposed root-owned locale caches under `umask=0027`.
The fixture runner now uses each image's web account, with a regression that
resolves the plugin label through core caches. The release gate requires the
clean database/browser/package workflow as well as the PHP matrix; current runs
are available in [GitHub Actions](https://github.com/ssemerikov/reviewerCertificate/actions).

Independent read-only review found no blocking production issue in the versioned
upgrade callback. Its integration fixture represents the old plugin object with
a stand-in rather than loading the entire old core class. Fresh schema creation
and customized-mail preservation are covered separately, not established from
scratch by that warm-upgrade fixture. Actual uploaded archive extraction and
replacement are not tested against the read-only plugin mount.

PostgreSQL tests use real OJS framework/DAO code with core-shaped parent tables,
not a complete PostgreSQL OJS installation. The broader legacy browser suites
were not run as part of this final gate.

Three local 1.10.0 release candidates were rebuilt at approximately 2.1 MB each.
Production dependency resolution reported no security advisories. Packages exclude
`AGENTS.md`, `CLAUDE.md`, development tests and obsolete SQL/XML schema installers.
PR #75 was closed with a thank-you explaining its equivalent incorporation.
Published packages and their validation links belong in the
[version-specific releases](https://github.com/ssemerikov/reviewerCertificate/releases);
GitHub's automatic source archives do not contain bundled dependencies.
