<?php
/**
 * Uninstall cleanup.
 *
 * The plugin gates at render time and never rewrites post content in the
 * database (PLAN.md §9.10), so uninstalling only needs to remove its own
 * option. The plugin writes no postmeta, no transients and no user meta,
 * so there is nothing else to clean up.
 *
 * @package ConsentGate
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'consent_gate_options' );
