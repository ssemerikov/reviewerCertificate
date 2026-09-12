# PKP review follow-up implementation plan

## Scope and constraints

Implement the items in GitHub issues #76 and #77 alongside the comprehensive reliability plan. Preserve existing certificates, customized mail templates, PHP/OJS compatibility and AGENTS.md. The user subsequently authorized GitHub issue replies and releases using the provided credentials; publish only after verification and never expose credentials. Use real core APIs and tests before removing compatibility branches.

## Work items

- [x] Verify both reports and comments (both had zero comments on 2026-09-11).
- [x] Confirm shared CSRF guards, OJS database connection and random upload names are already implemented; real MySQL checks pass on three OJS versions.
- [x] File lifecycle: add the core FileManager compatibility alias; use uploadFile/mkdirtree/deleteByPath and configured file modes, including generated background caches. Exercise actual multipart uploads with a restrictive umask and rollback/path-safety tests.
- [x] Mail registration: test installing the shipped manifest with each core email DAO. Replace obsolete email_texts with version-correct emails metadata referencing PO keys; preserve customized templates on upgrade and test retrieval after installation.
- [x] Referential integrity: inspect real column types and add compatible foreign keys to fresh schemas. Add safe upgrade constraints only when existing rows/types permit; report historical orphans without deleting certificate data. Test parent deletion and repeat migration.
- [x] Release packaging: resolve dependencies for each supported OJS PHP floor; package only applicable PO directories (keep source XML for translation maintenance), omit unused TCPDF examples/tools and verify PDFs from each extracted package.
- [x] JavaScript/compatibility cleanup: remove the unimplemented availability AJAX call if unreferenced; simplify aliases covered by the compatibility loader while retaining genuinely different OJS APIs.
- [x] Verify serial PHP suites, real DB/mail/browser checks, archive contents/size, dependency audit and documentation. Record any remaining PostgreSQL/concurrency coverage limits explicitly.

## Evidence

Final local results and remaining coverage limits are recorded in [the verification report](reliability-1.10-verification.md). PR #75's PostgreSQL HAVING fix is preserved equivalently in the newer aggregate-based picker and verified against both engines on all three OJS frameworks. Integration/publication of the development branch is a separate pending step.

Source reports: https://github.com/ssemerikov/reviewerCertificate/issues/76 and https://github.com/ssemerikov/reviewerCertificate/issues/77. The shipped OJS 3.3/3.5 email DAOs parse `email` records, not the plugin's existing `email_text` records. OJS FileManager::uploadFile applies the configured umask, which direct move_uploaded_file bypasses. Runtime locale files are PO; long/short XML copies remain useful source artifacts but need not ship in release archives.
