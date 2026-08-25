#!/usr/bin/env bash
# Bring up the WordPress integration stack and make it test-ready:
# install core, activate the plugin, seed the content (tests/wp/seed.php).
# Idempotent — safe to re-run; it only installs/seeds what is missing.
#
# Usage:            bash tests/wp/setup.sh
# Then run tests:   WP_BASE_URL=http://127.0.0.1:8890 npm run test:wp
# Tear down:        bash tests/wp/teardown.sh
set -euo pipefail
cd "$(dirname "$0")"

PORT="${CG_WP_PORT:-8890}"
URL="http://127.0.0.1:${PORT}"

compose() { docker compose -f docker-compose.yml "$@"; }
wpcli()   { compose run --rm --no-deps cli wp "$@"; }

compose up -d --wait db wordpress

# The wordpress image copies core into the volume on first boot; wait for it.
echo "Waiting for WordPress core files..."
for _ in $(seq 1 60); do
  if wpcli core version >/dev/null 2>&1; then break; fi
  sleep 2
done

if ! wpcli core is-installed >/dev/null 2>&1; then
  echo "Installing WordPress at ${URL}..."
  wpcli core install \
    --url="${URL}" \
    --title="Calucon Third-Party Embed Gate Test Site" \
    --admin_user=admin \
    --admin_password=password \
    --admin_email=admin@example.test \
    --skip-email
fi

# The wp-cli container writes as a different user than the one that owns the
# volume, and wp-content/mu-plugins does not exist in a fresh install at all.
# seed.php needs both: it drops a small mu-plugin in to emulate resource hints.
# Only these two directories — a recursive chown would hit the read-only bind
# mount of the plugin itself and fail.
compose exec -u root -T wordpress sh -c '
  mkdir -p wp-content/mu-plugins wp-content/uploads &&
  chmod 777 wp-content/mu-plugins wp-content/uploads
' >/dev/null

wpcli plugin activate calucon-third-party-embed-gate
wpcli eval-file /var/www/html/wp-content/plugins/calucon-third-party-embed-gate/tests/wp/seed.php

echo
echo "Ready: ${URL}  (admin / password)"
echo "Run:   WP_BASE_URL=${URL} npm run test:wp"
