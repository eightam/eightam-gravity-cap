#!/bin/bash
#
# Build a distributable zip for the Gravity Forms Cap CAPTCHA plugin.
# Output: ../eightam-gravity-cap_VERSION.zip
#

set -e

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_SLUG="eightam-gravity-cap"
MAIN_FILE="$PLUGIN_DIR/gravity-cap.php"

# Extract version from plugin header.
VERSION=$(grep -m1 "Version:" "$MAIN_FILE" | sed 's/.*Version:[[:space:]]*//' | sed 's/[[:space:]]*$//')

if [ -z "$VERSION" ]; then
	echo "Error: Could not extract version from $MAIN_FILE"
	exit 1
fi

OUTPUT_FILE="$(dirname "$PLUGIN_DIR")/${PLUGIN_SLUG}_${VERSION}.zip"

# Remove old zip if it exists.
rm -f "$OUTPUT_FILE"

# Build zip from the parent directory so the archive root is eightam-gravity-cap/.
cd "$(dirname "$PLUGIN_DIR")"

zip -r "$OUTPUT_FILE" \
	"$PLUGIN_SLUG/gravity-cap.php" \
	"$PLUGIN_SLUG/readme.txt" \
	"$PLUGIN_SLUG/includes/" \
	"$PLUGIN_SLUG/assets/" \
	"$PLUGIN_SLUG/languages/" \
	-x "*.DS_Store" \
	-x "*__MACOSX*"

echo ""
echo "Built: $OUTPUT_FILE"
echo "Size:  $(du -h "$OUTPUT_FILE" | cut -f1)"
