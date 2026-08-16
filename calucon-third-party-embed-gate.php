<?php
/**
 * Plugin Name:       Calucon Third-Party Embed Gate
 * Plugin URI:        https://calucon.de/third-party-embed-gate/
 * Description:       Two-click embeds: third-party iframes load only after the visitor asks for them. No banner, no consent platform, no third-party request before the click.
 * Version:           0.8.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Calucon
 * Author URI:        https://calucon.de
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       calucon-third-party-embed-gate
 * Domain Path:       /languages
 *
 * @package ConsentGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CALUCON_EMBED_GATE_VERSION', '0.8.1' );
define( 'CALUCON_EMBED_GATE_FILE', __FILE__ );
define( 'CALUCON_EMBED_GATE_DIR', __DIR__ );

/*
 * Back-compat aliases. The child theme on calucon.de disables its own embed
 * gate while this plugin is active by testing for the version constant; if it
 * stops being detected, both gates run and every embed gets two opt-in panels.
 * The theme accepts either name now, but a plugin update and a theme deploy are
 * not atomic. Keep these until at least 0.9.0 and remove them in a release of
 * their own, never alongside other changes.
 */
define( 'CONSENT_GATE_VERSION', CALUCON_EMBED_GATE_VERSION );
define( 'CONSENT_GATE_FILE', CALUCON_EMBED_GATE_FILE );
define( 'CONSENT_GATE_DIR', CALUCON_EMBED_GATE_DIR );

spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'ConsentGate\\' ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( 'ConsentGate\\' ) );
		$path     = CALUCON_EMBED_GATE_DIR . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

add_action( 'plugins_loaded', array( 'ConsentGate\\Plugin', 'boot' ) );
