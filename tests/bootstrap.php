<?php
/**
 * PHPUnit bootstrap.
 *
 * Defines the ABSPATH sentinel the plugin's `defined( 'ABSPATH' )` direct-
 * access guards check for, so the WordPress-free classes still load and run
 * under the unit/fixture suite without booting WordPress (PLAN.md §2.2). The
 * value is never read — only its presence marks "loaded from within the app".
 *
 * @package ConsentGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
