#!/bin/bash
# Point the OJS test containers at the Mailpit SMTP sink so E2E tests can
# assert real email delivery (my-certificates-email.spec.ts).
#
# Edits the ORIGINAL [email] keys in place. Do NOT append a second [email]
# section: parse_ini semantics make a duplicate section REPLACE the first,
# and OJS 3.4+ selects the transport via the section's `default` key.
# Idempotent.

set -euo pipefail

for c in ojs-test-ojs33-1 ojs-test-ojs34-1 ojs-test-ojs35-1; do
  docker exec "$c" sh -c '
    CFG=/var/www/html/config.inc.php
    # Remove any legacy appended override block from earlier script versions
    sed -i "/^; MAILPIT test overrides$/,/^smtp_auth = $/d" "$CFG"
    # OJS 3.4/3.5: Laravel mailer transport
    sed -i "s/^default = sendmail/default = smtp/" "$CFG"
    # OJS 3.3 toggle + shared SMTP host settings
    sed -i "s/^; smtp = On/smtp = On/" "$CFG"
    sed -i "s/^; smtp_server = mail.example.com/smtp_server = mailpit/" "$CFG"
    sed -i "s/^; smtp_port = 25/smtp_port = 1025/" "$CFG"
  '
  echo "$c: SMTP -> mailpit:1025"
done
