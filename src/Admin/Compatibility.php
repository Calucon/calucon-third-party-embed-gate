<?php
/**
 * Environment detection for the Compatibility screen (PLAN.md §7.1): the
 * detected CMP, cache plugin and page builder, and what Consent Gate
 * decided to do about each. Read-only.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConsentGate\Cmp\Detector;

/**
 * Detection is by constants/classes the plugins themselves define — cheap,
 * and no dependency on is_plugin_active() or the plugins screen.
 */
final class Compatibility {

	/**
	 * @return array[] Rows: name, kind ('cache'|'cmp'|'builder'); CMP rows
	 *                 carry 'tested' — whether the platform is on the §6.4
	 *                 bridge list.
	 */
	public static function detect(): array {
		$found = array();

		$cache_plugins = array(
			'WP Rocket'            => defined( 'WP_ROCKET_VERSION' ),
			'W3 Total Cache'       => defined( 'W3TC' ),
			'WP Super Cache'       => function_exists( 'wp_cache_clear_cache' ),
			'LiteSpeed Cache'      => defined( 'LSCWP_V' ),
			'WP Fastest Cache'     => class_exists( 'WpFastestCache' ),
			'Autoptimize'          => defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ),
			'SiteGround Optimizer' => function_exists( 'sg_cachepress_purge_cache' ),
			'Cloudflare'           => defined( 'CLOUDFLARE_PLUGIN_DIR' ),
		);
		foreach ( $cache_plugins as $name => $active ) {
			if ( $active ) {
				$found[] = array(
					'name' => $name,
					'kind' => 'cache',
				);
			}
		}

		// CMP rows come from the §6.4 bridge detector — one source of truth
		// for "which platform is installed and is it on the tested list".
		foreach ( Detector::detected() as $cmp ) {
			$found[] = array(
				'name'   => $cmp['label'],
				'kind'   => 'cmp',
				'tested' => true,
			);
		}
		foreach ( Detector::detected_untested() as $cmp ) {
			$found[] = array(
				'name'   => $cmp['label'],
				'kind'   => 'cmp',
				'tested' => false,
			);
		}

		$builders = array(
			'Elementor'      => defined( 'ELEMENTOR_VERSION' ),
			'WPBakery'       => defined( 'WPB_VC_VERSION' ),
			'Divi'           => defined( 'ET_BUILDER_VERSION' ) || 'Divi' === wp_get_theme()->get( 'Name' ),
			'Beaver Builder' => defined( 'FL_BUILDER_VERSION' ),
			'Bricks'         => 'Bricks' === wp_get_theme()->get( 'Name' ),
			'Oxygen'         => defined( 'CT_VERSION' ),
		);
		foreach ( $builders as $name => $active ) {
			if ( $active ) {
				$found[] = array(
					'name' => $name,
					'kind' => 'builder',
				);
			}
		}

		return $found;
	}

	/**
	 * Scan the active theme's (and parent theme's) CSS and functions.php for
	 * third-party asset hosts — fonts are the single most-litigated
	 * third-party request (§3.5), and they load from the theme, not from
	 * content. Local file reads only; nothing is fetched.
	 *
	 * @return array[] Rows: file (relative), hosts (string[]).
	 */
	public static function theme_asset_findings(): array {
		$dirs = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );

		$pattern  = '#(?:fonts\.googleapis\.com|fonts\.gstatic\.com|use\.typekit\.net|kit\.fontawesome\.com|fonts\.bunny\.net|cdnjs\.cloudflare\.com|ajax\.googleapis\.com|maxcdn\.bootstrapcdn\.com|cdn\.jsdelivr\.net|unpkg\.com)#i';
		$findings = array();
		$budget   = 40; // Files, not bytes: bounded work on huge themes.

		foreach ( $dirs as $dir ) {
			if ( ! is_string( $dir ) || '' === $dir || ! is_dir( $dir ) ) {
				continue;
			}
			$files = array_merge(
				array( $dir . '/functions.php' ),
				(array) glob( $dir . '/*.css' ),
				(array) glob( $dir . '/assets/css/*.css' ),
				(array) glob( $dir . '/css/*.css' )
			);
			foreach ( $files as $file ) {
				if ( $budget <= 0 ) {
					break 2;
				}
				if ( ! is_string( $file ) || ! is_file( $file ) || filesize( $file ) > 2097152 ) {
					continue;
				}
				--$budget;
				$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file, read-only scan.
				if ( preg_match_all( $pattern, $contents, $m ) ) {
					$findings[] = array(
						'file'  => ltrim( str_replace( dirname( $dir ), '', $file ), '/' ),
						'hosts' => array_values( array_unique( array_map( 'strtolower', $m[0] ) ) ),
					);
				}
			}
		}

		return $findings;
	}
}
