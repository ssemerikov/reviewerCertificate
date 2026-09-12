# Reviewer Certificate Plugin — Installation Guide

## Install or upgrade through OJS

Back up the database and journal files first. Download the asset matching your OJS version from [GitHub Releases](https://github.com/ssemerikov/reviewerCertificate/releases):

- `reviewerCertificate-{VERSION}-3_3.tar.gz`: OJS 3.3, PHP 7.3 or newer.
- `reviewerCertificate-{VERSION}-3_4.tar.gz`: OJS 3.4, PHP 8.0.2 or newer.
- `reviewerCertificate-{VERSION}-3_5.tar.gz`: OJS 3.5, PHP 8.2 or newer.

Use the named asset, not GitHub's automatic source archive: release assets include TCPDF and the correct runtime locales. Follow your OJS release's own PHP requirements as well.

As an administrator, open **Settings → Website → Plugins**, upload the package through OJS's plugin installation/upgrade workflow, then enable the plugin. Open **Settings** and preview a certificate before routine use. SMTP must already be configured in OJS for email delivery.

## Upgrading to 1.10

Copying files alone does not apply database changes. The OJS upgrade workflow runs `upgrade.xml` and an additive Laravel migration. It creates the notification ledger and adds compatible foreign keys without replacing existing certificates or verification codes. Repeated upgrades are safe; automatic schema downgrade is unsupported.

The versioned upgrade entry point handles old plugin classes still loaded during an uploaded upgrade, installs the version-appropriate email manifest and retires only this plugin's superseded schema/email callbacks.

Older rows with missing parent records are preserved. The PHP error log reports each deferred foreign key and its orphan count. Reconcile those records after a backup, then rerun the upgrade to install the deferred constraints. Deleting a review, submission, reviewer, or journal cascades to its certificates and notification history. Deleting a certificate template preserves certificates and clears their optional template reference.

New certificates require the configured number of completed, nondeclined, noncancelled reviews in the same journal. Existing certificates remain available after a threshold increase, subject to ownership and completion checks.

Ordinary batch generation does not send mail. Use **Historical certificate notifications** to explicitly announce older certificates. Pre-upgrade delivery history is unknown, so this may duplicate announcements sent before tracking existed. Interrupted deliveries become **uncertain** after ten minutes; check SMTP logs before explicitly retrying. Transport acceptance is not proof of inbox delivery.

If a completion hook runs inside an open database transaction, notification delivery is deferred to avoid emailing about uncommitted certificates. After that transaction commits, use historical notifications to send it; no automatic after-commit job is scheduled. A thrown transport exception is also treated as uncertain, not proof that no message was sent.

## Source installations and development

Clone into `plugins/generic/reviewerCertificate`, then run `composer install --no-dev` with the server's supported PHP version. Development installations use `composer install` to include PHPUnit. Register/install the plugin through OJS; enabling copied source is not a substitute for applying its migration. See [the test environment guide](tests/environment/README.md) for disposable development instances.

Keep source files readable by the web server and the configured OJS `files_dir` writable. Uploaded backgrounds and cached derivatives honor `files.umask`; avoid blanket world-writable permissions. Release builds use `./release.sh <version>` and preserve development dependencies.

## Troubleshooting

For missing-table, migration, or invalid-JSON errors, inspect the OJS/PHP logs and confirm that the correct package was installed through OJS. The plugin uses OJS's configured database connection, including its port, socket, and TLS options. Legacy MySQL-only installation/uninstallation scripts are no longer provided; do not import old SQL as a migration workaround.

Report the OJS/PHP/database versions and sanitized error details in [GitHub Issues](https://github.com/ssemerikov/reviewerCertificate/issues). Never include credentials or private review content.
