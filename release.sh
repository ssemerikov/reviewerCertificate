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
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo 'Version must use major.minor.patch format.' >&2
  exit 1
fi
PLUGIN_NAME="reviewerCertificate"
BUILD_DIR="$(mktemp -d)"
trap 'rm -rf "$BUILD_DIR"' EXIT

echo "Building release v${VERSION}..."

# Install production dependencies in staging, preserving the development vendor/.
COMPOSER_CMD=(composer)
if ! command -v composer &>/dev/null; then
  if [ -f "composer.phar" ]; then
    COMPOSER_CMD=(php "$PWD/composer.phar")
  else
    echo "Error: composer not found. Install it or place composer.phar in the plugin directory."
    exit 1
  fi
fi
for OJS_VERSION in 3.3 3.4 3.5; do
  case "$OJS_VERSION" in
    3.3) PHP_FLOOR=7.3.0 ;;
    3.4) PHP_FLOOR=8.0.2 ;;
    3.5) PHP_FLOOR=8.2.0 ;;
  esac
  DEPS_DIR="$BUILD_DIR/dependencies-$OJS_VERSION"
  mkdir -p "$DEPS_DIR"
  php temp/prepare_release_dependencies.php "$DEPS_DIR"
  "${COMPOSER_CMD[@]}" config --working-dir="$DEPS_DIR" platform.php "$PHP_FLOOR"
  "${COMPOSER_CMD[@]}" require --working-dir="$DEPS_DIR" --no-update "php:>=$PHP_FLOOR"
  # Refresh the staging lock's platform snapshot without changing pinned versions.
  if [ -f "$DEPS_DIR/composer.lock" ]; then
    "${COMPOSER_CMD[@]}" update --working-dir="$DEPS_DIR" --lock --no-install --no-dev --no-interaction
  fi
  "${COMPOSER_CMD[@]}" install --working-dir="$DEPS_DIR" --no-dev --no-interaction --prefer-dist
  DEST="${BUILD_DIR}/${PLUGIN_NAME}"
  rm -rf "$DEST"
  mkdir -p "$DEST"

  # Copy plugin source files
  cp -r ReviewerCertificatePlugin.php version.xml index.php "$DEST/"
  cp -r classes/ controllers/ templates/ css/ js/ "$DEST/"
  for LOCALE_DIR in locale/*; do
    LOCALE_NAME="${LOCALE_DIR##*/}"
    if [ "$OJS_VERSION" = '3.3' ]; then
      [[ "$LOCALE_NAME" == *_* ]] || continue
      [[ "$LOCALE_NAME" != 'zh_Hans' ]] || continue
    else
      [[ "$LOCALE_NAME" != *_* || "$LOCALE_NAME" = 'pt_BR' || "$LOCALE_NAME" = 'zh_Hans' ]] || continue
    fi
    mkdir -p "$DEST/locale/$LOCALE_NAME"
    cp "$LOCALE_DIR/locale.po" "$LOCALE_DIR/emails.po" "$DEST/locale/$LOCALE_NAME/"
  done

  # Copy email templates (required for installEmailTemplates)
  [ -f emailTemplates.xml ] && cp emailTemplates.xml "$DEST/"
  [ "$OJS_VERSION" != "3.3" ] || cp emailTemplates-3.3.xml "$DEST/"
  cp upgrade.xml "$DEST/"

  # Copy compat_autoloader only for OJS 3.3 (causes fatal errors on 3.4+)
  if [ "$OJS_VERSION" = "3.3" ] && [ -f compat_autoloader.php ]; then
    cp compat_autoloader.php "$DEST/"
  fi

  # Bundle Composer autoloader + TCPDF (required — no OJS version ships it)
  mkdir -p "$DEST/vendor"
  cp "$DEPS_DIR/vendor/autoload.php" "$DEST/vendor/"
  cp -r "$DEPS_DIR/vendor/composer/" "$DEST/vendor/"
  TCPDF_SOURCE="$DEPS_DIR/vendor/tecnickcom/tcpdf"
  TCPDF_DEST="$DEST/vendor/tecnickcom/tcpdf"
  mkdir -p "$TCPDF_DEST/fonts"
  cp "$TCPDF_SOURCE/"*.php "$TCPDF_SOURCE/LICENSE.TXT" "$TCPDF_SOURCE/composer.json" "$TCPDF_DEST/"
  cp -r "$TCPDF_SOURCE/include" "$TCPDF_SOURCE/config" "$TCPDF_DEST/"
  # All configured fonts and their bold/italic variants; keep required licenses.
  for FONT in "$TCPDF_SOURCE/fonts/"*; do
    FONT_NAME="${FONT##*/}"
    if [[ "$FONT_NAME" =~ ^(helvetica|times|courier|dejavusans|symbol|zapfdingbats)(b|i|bi)?\.(php|z|ctg\.z)$ ]]; then
      cp "$FONT" "$TCPDF_DEST/fonts/"
    elif [[ -d "$FONT" && "$FONT_NAME" == dejavu-fonts-ttf-* ]]; then
      mkdir -p "$TCPDF_DEST/fonts/$FONT_NAME"
      cp "$FONT/LICENSE" "$FONT/README" "$TCPDF_DEST/fonts/$FONT_NAME/"
    fi
  done

  # Copy metadata files
  cp "$DEPS_DIR/composer.json" "$DEST/"
  for f in README.md INSTALL.md LICENSE CHANGELOG.md; do
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
