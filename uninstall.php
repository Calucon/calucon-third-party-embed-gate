<?php
/**
 * Uninstall cleanup.
 *
 * The plugin gates at render time and never rewrites post content in the
 * database (PLAN.md §9.10), so uninstalling only needs to remove its own
 * option — on every site of a network, not just the one running the
 * uninstall (§9.11). The plugin writes no postmeta, no transients and no
 * user meta, so there is nothing else to clean up.
 *
 * @package CaluconEmbedGate
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $calucon_embed_gate_site_id ) {
		delete_blog_option( $calucon_embed_gate_site_id, 'calucon_embed_gate_options' );
	}
	unset( $calucon_embed_gate_site_id );
} else {
	delete_option( 'calucon_embed_gate_options' );
}
