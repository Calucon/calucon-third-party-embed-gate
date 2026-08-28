#!/usr/bin/env bash
# The field-validation groups: which real wordpress.org plugins are installed
# into a fresh Docker WordPress before tests/Field/<group>.spec.js runs.
#
# This file is the single source of truth for both runners — tests/wp/field.sh
# locally and .github/workflows/field-validation.yml (whose matrix is built
# from `--list-json`) — so a group added here cannot be forgotten in CI.
#
# One group = one fresh stack. CMPs compete for Detector::detected()'s
# priority order, and cache plugins write drop-ins, wp-config.php constants
# and .htaccess blocks outside their own directory, so two of them on one
# install would test each other rather than the plugin.
#
# Plugins that cannot be installed without a licence or an account (WP Rocket,
# Borlabs Cookie, WPBakery, Divi, Bricks, Oxygen, WPML, Weglot, Usercentrics,
# iubenda, Cookiebot's banner itself) stay emulated-only in tests/wp/seed.php;
# docs/field-validation.md lists them.

# id                        wordpress.org slugs (space-separated)
field_groups() {
	cat <<'EOF'
cmp-complianz             complianz-gdpr
cmp-cookieyes             cookie-law-info
cmp-wp-consent-api        wp-consent-api
cmp-real-cookie-banner    real-cookie-banner
cache-w3-total-cache      w3-total-cache
cache-wp-super-cache      wp-super-cache
cache-litespeed           litespeed-cache
cache-autoptimize         autoptimize
cache-wp-fastest-cache    wp-fastest-cache
cache-sg-cachepress       sg-cachepress
builder-elementor         elementor
detect-only               cookiebot cloudflare polylang translatepress-multilingual beaver-builder-lite-version
EOF
}

field_group_ids() {
	field_groups | awk '{ print $1 }'
}

# Slugs of one group; exit 1 (and print nothing) for an unknown id.
field_group_slugs() {
	local line
	line=$(field_groups | awk -v id="$1" '$1 == id { $1 = ""; print }')
	[ -n "$line" ] || return 1
	echo "$line" | xargs
}

if [ "${1:-}" = "--list-json" ]; then
	field_group_ids | awk 'BEGIN { printf "[" } { printf "%s\"%s\"", (NR > 1 ? "," : ""), $0 } END { print "]" }'
elif [ "${1:-}" = "--list" ]; then
	field_group_ids
fi
