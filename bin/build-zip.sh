#!/usr/bin/env bash
#
# Build a distributable WooCommerce plugin ZIP.
#
# The ZIP contains a single top-level directory named after the plugin slug
# ("kenzi-commerce") with only production files — no tests, dev config,
# or dev dependencies. The output is written to the current working directory.
#
# Usage:  bash bin/build-zip.sh          (run from platforms/woocommerce/)

set -euo pipefail

SLUG="kenzi-commerce"
ZIP_NAME="${SLUG}.zip"
BUILD_DIR=$(mktemp -d)
DEST="${BUILD_DIR}/${SLUG}"

cleanup() { rm -rf "$BUILD_DIR"; }
trap cleanup EXIT

mkdir -p "$DEST"

# Copy production files
cp -r src/ assets/ "$DEST/"
cp kenzi-commerce.php uninstall.php LICENSE composer.json "$DEST/"

# Install production autoloader (no dev dependencies)
composer install --no-dev --optimize-autoloader --no-interaction --quiet --working-dir="$DEST"

# Remove composer artefacts that aren't needed at runtime
rm -f "$DEST/composer.json" "$DEST/composer.lock"

# Create the ZIP
(cd "$BUILD_DIR" && zip -rq "$OLDPWD/$ZIP_NAME" "$SLUG")

echo "Built $ZIP_NAME"
