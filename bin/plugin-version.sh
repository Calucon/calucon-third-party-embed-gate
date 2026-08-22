#!/usr/bin/env bash
# Print the plugin version from the main file's header — the single source
# of truth shared by bin/build-zip.sh and .github/workflows/release.yml, so
# the release tag and the packaged zip can never disagree.
set -euo pipefail
cd "$(dirname "$0")/.."
sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9][0-9A-Za-z.\-]*)[[:space:]]*$/\1/p' calucon-third-party-embed-gate.php | head -n1
