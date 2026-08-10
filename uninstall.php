<?php
/**
 * Uninstall cleanup.
 *
 * The plugin gates at render time and never rewrites post content in the
 * database (PLAN.md §9.10), so uninstalling only needs to remove its own
 * option. oEmbed postmeta caches are untouched by M1 (no embed_oembed_html
 * hook yet); flush them here when that integration lands.
 *
 * @package ConsentGate
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'consent_gate_options' );
