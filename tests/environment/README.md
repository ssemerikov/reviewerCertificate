# Isolated integration tests

Prerequisites: Docker Compose, PHP, Composer, Node.js 22+ and Playwright Chromium.

```sh
composer install
npm install
npx playwright install chromium
node tests/environment/setup.mjs
npx playwright test tests/e2e/batch-generation.spec.ts --workers=1
```

The `reviewer-certificate-tests` Compose project uses dedicated named volumes. Setup never deletes volumes and does not use the old untracked `ojs-test/` installation. It installs OJS 3.3/3.4/3.5, seeds disposable journal data, configures journal locales and SMTP, and checks the additive plugin migration.

OJS listens on localhost ports 8033–8035; Mailpit is on 8125. Test users `testadmin`, `testeditor`, `testreviewer`, and `testauthor` use `testpass123`. Credentials and published ports are for local testing only. Do not expose this stack publicly.

Run a database check independently:

```sh
docker compose -f tests/environment/docker-compose.yml exec -T \
  -e OJS_TEST_DATABASE=ojs34 ojs34 php \
  plugins/generic/reviewerCertificate/tests/environment/check-database.php
```

Repeat with `ojs33`/`ojs35`. Checks exercise the real DAO, 505-review pagination, thresholds, duplicate issuance, notification claims and repeatable migrations. Temporary review/certificate changes roll back; plugin tables are retained. `check-mail.php` sends one test notification through the actual OJS transport to Mailpit.

Browser suites share databases: keep one worker. Set `PLAYWRIGHT_CHROMIUM_EXECUTABLE` only when using a preinstalled compatible Chromium. `OJS_TEST_COMPOSE` selects a different disposable Compose file; never point it at production.

`check-warm-upgrade.php` runs the real installer with the 1.9 migration still loaded and a stand-in for the old plugin object, modeling PHP's same-request upload behavior. It checks certificate preservation, localized mail and unrelated hooks. Fresh schema creation and customized-mail preservation are tested separately. It does not load the entire old plugin core or exercise archive extraction/replacement of the read-only plugin mount.

The OJS installations use MySQL. The optional `docker-compose.postgres.yml` adds a disposable PostgreSQL service. `check-postgres.php` accepts `OJS_TEST_DATABASE=rc_postgres_test` or `rc_mysql_test` and creates a randomly named schema/database with core-shaped parent tables. It checks fresh/repeated migrations, orphan preservation and deterministic two-connection issuance/notification races inside outer transactions. This is not a full PostgreSQL OJS installation. See the integration workflow for commands and required PDO drivers.

Do not run database-mutating checks alongside browser tests against the same project. The broader legacy browser suites may need additional fixtures beyond this minimal environment.

Stop without deleting data:

```sh
docker compose -f tests/environment/docker-compose.yml stop
```
