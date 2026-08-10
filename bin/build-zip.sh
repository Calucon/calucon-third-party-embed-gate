#!/usr/bin/env bash
# Build the installable plugin zip: production files only, wrapped in a
# consent-gate/ directory so it extracts as a proper plugin folder.
# Used by .github/workflows/release.yml and runnable locally:
#
#   bash bin/build-zip.sh   ->  build/consent-gate-<version>.zip
set -euo pipefail
cd "$(dirname "$0")/.."

VERSION=$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9][0-9A-Za-z.\-]*)[[:space:]]*$/\1/p' consent-gate.php | head -n1)
if [ -z "$VERSION" ]; then
	echo "Could not read the Version header from consent-gate.php" >&2
	exit 1
fi

STAGE="build/consent-gate"
ZIP="build/consent-gate-${VERSION}.zip"

rm -rf "$STAGE" "$ZIP"
mkdir -p "$STAGE"

# The production surface, nothing else: no tests, no dev tooling, no
# node_modules/vendor (the plugin has no runtime Composer dependencies).
# docs/ ships deliberately: customizing.md is the on-site reference for
# developers and AI agents (the deep docs stay in the repo).
cp consent-gate.php uninstall.php readme.txt LICENSE "$STAGE/"
cp -R src assets templates languages docs "$STAGE/"

( cd build && zip -rq "consent-gate-${VERSION}.zip" consent-gate )

echo "Built $ZIP:"
unzip -l "$ZIP"
