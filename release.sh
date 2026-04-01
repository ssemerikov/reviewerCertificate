#!/bin/bash
# Build release tar.gz packages for OJS Plugin Gallery distribution.
# Produces: reviewerCertificate-{VERSION}-3_3.tar.gz, -3_4.tar.gz, -3_5.tar.gz
#
# Each package includes vendor/tecnickcom/tcpdf/ since no OJS version ships
# TCPDF natively and OJS ZIP upload has no composer install step.
#
# Usage: ./release.sh 1.6.0

set -euo pipefail

VERSION="${1:?Usage: ./release.sh <version>  (e.g., ./release.sh 1.6.0)}"
PLUGIN_NAME="reviewerCertificate"
BUILD_DIR="$(mktemp -d)"
trap 'rm -rf "$BUILD_DIR"' EXIT

echo "Building release v${VERSION}..."

# Install production dependencies only
COMPOSER_CMD="composer"
if ! command -v composer &>/dev/null; then
  if [ -f "composer.phar" ]; then
    COMPOSER_CMD="php composer.phar"
  else
    echo "Error: composer not found. Install it or place composer.phar in the plugin directory."
    exit 1
  fi
fi
$COMPOSER_CMD install --no-dev --no-autoloader --no-interaction
$COMPOSER_CMD dump-autoload --no-dev --optimize 2>/dev/null || $COMPOSER_CMD dump-autoload --no-dev

for OJS_VERSION in 3.3 3.4 3.5; do
  DEST="${BUILD_DIR}/${PLUGIN_NAME}"
  rm -rf "$DEST"
  mkdir -p "$DEST"

  # Copy plugin source files
  cp -r ReviewerCertificatePlugin.php version.xml index.php "$DEST/"
  cp -r classes/ controllers/ locale/ templates/ "$DEST/"

  # Copy compat_autoloader only for OJS 3.3 (causes fatal errors on 3.4+)
  if [ "$OJS_VERSION" = "3.3" ] && [ -f compat_autoloader.php ]; then
    cp compat_autoloader.php "$DEST/"
  fi

  # Bundle Composer autoloader + TCPDF (required — no OJS version ships it)
  mkdir -p "$DEST/vendor"
  cp vendor/autoload.php "$DEST/vendor/"
  cp -r vendor/composer/ "$DEST/vendor/"
  cp -r vendor/tecnickcom/ "$DEST/vendor/"

  # Copy metadata files
  for f in composer.json README.md LICENSE CHANGELOG.md; do
    [ -f "$f" ] && cp "$f" "$DEST/"
  done

  # Build archive (OJS Plugin Gallery standard format)
  ARCHIVE="${PLUGIN_NAME}-${VERSION}-${OJS_VERSION/./_}.tar.gz"
  tar -czf "$ARCHIVE" -C "$BUILD_DIR" "$PLUGIN_NAME"
  echo "  Created: $ARCHIVE ($(du -h "$ARCHIVE" | cut -f1))"
  rm -rf "$DEST"
done

echo ""
echo "Done. Upload to GitHub Releases as:"
echo "  v${VERSION}-3.3  v${VERSION}-3.4  v${VERSION}-3.5"
