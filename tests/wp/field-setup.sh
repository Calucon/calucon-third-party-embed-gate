#!/usr/bin/env bash
# Bring up a FRESH WordPress integration stack with one field-validation group's
# real wordpress.org plugins installed, configured and verified active.
#
# Usage:            bash tests/wp/field-setup.sh <group>      (ids: field-groups.sh --list)
# Then run tests:   WP_BASE_URL=http://127.0.0.1:8890 npm run test:field -- --project=<group>
# Tear down:        bash tests/wp/teardown.sh
#
# Every run starts from teardown: the plugins under test write drop-ins,
# wp-config.php constants and .htaccess rules outside their own directory,
# and their uninstallers are exactly the third-party code this suite does not
# trust to clean up. Optional FIELD_VERSIONS="slug=1.2.3 other=4.5" pins
# versions (reproducing a failure); the default is the current release,
# because rot detection is the point.
#
# The wordpress.org downloads are the harness contacting a third party, not
# the plugin: invariant 9 binds the plugin, and the CI-only privacy-link
# canary already set the precedent for the harness.
#
# Nothing here is a test. A failed install or an inactive plugin exits
# non-zero BEFORE Playwright starts, so a green suite can never mean "the
# plugin under test was not even there".
set -euo pipefail
cd "$(dirname "$0")"
# shellcheck source=field-groups.sh
source ./field-groups.sh

GROUP="${1:-}"
if [ -z "$GROUP" ]; then
	echo "usage: $0 <group>   (one of: $(field_group_ids | xargs))" >&2
	exit 2
fi
SLUGS=$(field_group_slugs "$GROUP") || { echo "unknown group '$GROUP' (one of: $(field_group_ids | xargs))" >&2; exit 2; }

PORT="${CG_WP_PORT:-8890}"
URL="http://127.0.0.1:${PORT}"
PLUGIN_DIR=/var/www/html/wp-content/plugins/calucon-third-party-embed-gate
# Not under test-results/: Playwright empties that directory when it starts.
RESULTS="$(cd ../.. && pwd)/field-results"

compose() { docker compose -f docker-compose.yml "$@"; }
# wp-cli as Apache's user (uid 33 owns the volume): plugin installs and the
# cache plugins' writes to wp-config.php / .htaccess / advanced-cache.php
# then run with exactly the permissions they would have on a real site. The
# uid has no passwd entry in the cli image, hence HOME and the cache dir.
wpwww() {
	compose run --rm --no-deps --user 33:33 -e HOME=/tmp -e WP_CLI_CACHE_DIR=/tmp/wp-cli-cache cli wp "$@"
}

# The version an optional FIELD_VERSIONS pin asks for, or nothing.
pinned_version() {
	local slug="$1" pair
	for pair in ${FIELD_VERSIONS:-}; do
		case "$pair" in "$slug="*) echo "${pair#*=}"; return ;; esac
	done
}

echo "== field-validation group: $GROUP  (plugins: $SLUGS)"
bash teardown.sh >/dev/null 2>&1 || true
bash setup.sh

mkdir -p "$RESULTS"

echo "== installing"
for slug in $SLUGS; do
	version=$(pinned_version "$slug")
	if [ -n "$version" ]; then
		wpwww plugin install "$slug" --version="$version" --activate --force
	else
		wpwww plugin install "$slug" --activate --force
	fi
done

# Per-plugin configuration so the behaviour under test is actually on. Every
# command here is "verify on first run": these are other people's option
# names and CLI commands, and a wrong one fails the group loudly rather than
# letting a misconfigured plugin pass as validated.
echo "== configuring"
case "$GROUP" in
	cmp-complianz)
		# Complianz enqueues nothing on the front end until (a) its wizard has
		# been completed once and (b) site_needs_cookie_warning() is true,
		# which on a site with no GTM and no blocked scripts means the wizard
		# answers "uses social media / third-party services". Both are plain
		# options; these are the answers the wizard would have stored.
		wpwww option update cmplz_wizard_completed_once 1
		wpwww option update cmplz_options '{"use_cdb_api":"yes","regions":["eu"],"uses_social_media":"yes","uses_thirdparty_services":"yes","uses_ad_cookies":"no","enable_cookie_banner":"yes"}' --format=json
		;;
	cmp-real-cookie-banner)
		# RCB ships with the banner OFF (its wizard switches it on); the free
		# tier has no YouTube content blocker, which is phase 1's premise.
		wpwww option update rcb-banner-active 1
		;;
	cache-w3-total-cache)
		wpwww w3-total-cache option set pgcache.enabled true --type=boolean
		wpwww w3-total-cache option set pgcache.engine file_generic --type=string
		wpwww w3-total-cache fix_environment apache
		;;
	cache-wp-super-cache)
		# Its admin UI writes WPCACHEHOME into wp-config.php on activation; a
		# wp-cli activation does not, and without it advanced-cache.php
		# never loads phase 1 — pages get written to the cache and never
		# served from it (the first field run found exactly that).
		wpwww config set WPCACHEHOME '/var/www/html/wp-content/plugins/wp-super-cache/' --type=constant
		wpwww eval 'if ( function_exists( "wp_cache_enable" ) ) { wp_cache_enable(); } if ( function_exists( "wp_super_cache_enable" ) ) { wp_super_cache_enable(); }'
		;;
	cache-litespeed)
		wpwww option update litespeed.conf.cache 1
		;;
	cache-autoptimize)
		wpwww option update autoptimize_js on
		wpwww option update autoptimize_js_aggregate on
		;;
	cache-wp-fastest-cache)
		wpwww option update WpFastestCache '{"wpFastestCacheStatus":"on","wpFastestCacheMinifyJs":"on","wpFastestCacheCombineJs":"on"}'
		;;
	cache-sg-cachepress)
		wpwww sg optimize combine-js enable || echo "::warning::sg optimize combine-js: command failed (verify on first run)"
		;;
	builder-elementor)
		# The two things a theme's "skip Elementor onboarding" helper does.
		wpwww transient delete elementor_activation_redirect || true
		wpwww option update elementor_onboarded 1
		# Elementor's default typography fetches Google Fonts on every page —
		# its own request, not an embed, and the switch an owner who cares
		# about third-party requests flips (Elementor → Settings → Advanced).
		wpwww option update elementor_google_font 0
		;;
esac

# The CMP groups run with the bridge ON at the option level; individual
# tests toggle it through the settings form where a state matters.
case "$GROUP" in
	cmp-*)
		wpwww option patch update calucon_embed_gate_options cmp bridge 1 2>/dev/null \
			|| wpwww option update calucon_embed_gate_options '{"cmp":{"bridge":true}}' --format=json
		;;
esac

echo "== seeding field pages and the probe"
wpwww eval-file "$PLUGIN_DIR/tests/wp/field-seed.php" "$GROUP"

echo "== verifying"
# Warm-up: some plugins redirect the first request after activation to a
# setup screen (a one-time transient). Consume it here, and insist the
# probe answers JSON, so no spec ever sees that redirect.
for _ in 1 2 3; do
	if curl -fsSL "$URL/?cg_field=status" | grep -q '"probe":1'; then break; fi
	sleep 1
done
curl -fsSL "$URL/?cg_field=status" | grep -q '"probe":1' || { echo "FIELD: the probe does not answer JSON at $URL/?cg_field=status" >&2; exit 1; }
for slug in $SLUGS; do
	if ! wpwww plugin is-active "$slug"; then
		echo "FIELD: $slug is installed but not active — a fatal on load deactivates a plugin silently. Group $GROUP cannot be validated." >&2
		exit 1
	fi
done
wpwww plugin list --format=json > "$RESULTS/$GROUP-plugins.json"
echo "installed:"; wpwww plugin list --fields=name,status,version

echo
echo "Ready: ${URL}  (admin / password)"
echo "Run:   WP_BASE_URL=${URL} npm run test:field -- --project=${GROUP}"
