<?php
/**
 * Plugin Name:       Consent Gate
 * Plugin URI:        https://calucon.de/consent-gate/
 * Description:       Two-click embeds: third-party iframes load only after the visitor asks for them. No banner, no consent platform, no third-party request before the click.
 * Version:           0.7.5
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Calucon
 * Author URI:        https://calucon.de
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       consent-gate
 * Domain Path:       /languages
 *
 * @package ConsentGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONSENT_GATE_VERSION', '0.7.5' );
define( 'CONSENT_GATE_FILE', __FILE__ );
define( 'CONSENT_GATE_DIR', __DIR__ );

spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'ConsentGate\\' ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( 'ConsentGate\\' ) );
		$path     = CONSENT_GATE_DIR . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

add_action( 'plugins_loaded', array( 'ConsentGate\\Plugin', 'boot' ) );
