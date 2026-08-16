#!/usr/bin/env bash
# No-Docker WordPress runner: WordPress Playground (PHP compiled to WASM,
# SQLite instead of MySQL) serving a real WordPress with the plugin mounted
# and the same seed content as the Docker stack. Used automatically by
# playwright.wp.config.js when WP_BASE_URL is not set.
#
# First run downloads WordPress once and caches it (~/.wordpress-playground).
set -euo pipefail
cd "$(dirname "$0")/../.."

# Corporate/CI TLS interception: trust the proxy CA if one is provided.
if [ -z "${NODE_EXTRA_CA_CERTS:-}" ] && [ -f /root/.ccr/ca-bundle.crt ]; then
  export NODE_EXTRA_CA_CERTS=/root/.ccr/ca-bundle.crt
fi

exec npx @wp-playground/cli@latest server \
  --port="${CG_WP_PORT:-8890}" \
  --blueprint=tests/wp/blueprint.json \
  --mount=.:/wordpress/wp-content/plugins/third-party-embed-gate
