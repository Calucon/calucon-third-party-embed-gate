<?php
/**
 * Environment detection for the Compatibility screen (PLAN.md §7.1): the
 * detected CMP, cache plugin and page builder, and what Calucon Third-Party Embed Gate
 * decided to do about each. Read-only.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Cmp\Detector;

/**
 * Detection is by constants/classes the plugins themselves define — cheap,
 * and no dependency on is_plugin_active() or the plugins screen.
 */
final class Compatibility {

	/**
	 * @return array[] Rows: name, kind ('cache'|'cmp'|'multilingual'|'builder');
	 *                 CMP rows carry 'tested' — whether the platform is on the
	 *                 §6.4 bridge list — and multilingual rows carry 'mode' and
	 *                 'where' (see multilingual_plugins()).
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

		foreach ( self::multilingual_plugins() as $name => $spec ) {
			if ( call_user_func( $spec['signal'] ) ) {
				$found[] = array(
					'name'  => $name,
					'kind'  => 'multilingual',
					'mode'  => $spec['mode'],
					'where' => $spec['where'],
				);
			}
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
	 * The multilingual plugins worth telling the owner about, and how each
	 * one gets at the texts the owner typed.
	 *
	 * Two models, and the difference decides what the Compatibility row says:
	 *
	 *  - 'registry' — WPML and Polylang translate registered option strings.
	 *    The plugin registers them in wpml-config.xml, which both read, but
	 *    the owner still has to go and translate them in a screen they may
	 *    never have opened. That screen is what 'where' names.
	 *  - 'output' — TranslatePress and Weglot translate the finished page, so
	 *    the panel's text is translated like any other text on it, and there
	 *    is nothing to register or configure.
	 *
	 * Kept as data so the unit suite can check it without WordPress.
	 *
	 * @return array[] name => array{ mode: string, where: string, signal: callable }
	 */
	public static function multilingual_plugins(): array {
		return array(
			'WPML'           => array(
				'mode'   => 'registry',
				'where'  => 'WPML → String Translation',
				'signal' => static function (): bool {
					return defined( 'ICL_SITEPRESS_VERSION' );
				},
			),
			'Polylang'       => array(
				'mode'   => 'registry',
				'where'  => 'Languages → Translations → Strings',
				'signal' => static function (): bool {
					return defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_the_languages' );
				},
			),
			'TranslatePress' => array(
				'mode'   => 'output',
				'where'  => '',
				'signal' => static function (): bool {
					return defined( 'TRP_PLUGIN_VERSION' );
				},
			),
			'Weglot'         => array(
				'mode'   => 'output',
				'where'  => '',
				'signal' => static function (): bool {
					return defined( 'WEGLOT_VERSION' );
				},
			),
		);
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

		// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- detection pattern only: local theme files are scanned for these CDN hosts so the Compatibility screen can WARN the owner; the plugin never requests them.
		$cdn_hosts_to_warn_about = '#(?:fonts\.googleapis\.com|fonts\.gstatic\.com|use\.typekit\.net|kit\.fontawesome\.com|fonts\.bunny\.net|cdnjs\.cloudflare\.com|ajax\.googleapis\.com|maxcdn\.bootstrapcdn\.com|cdn\.jsdelivr\.net|unpkg\.com)#i'; // Searched for INSIDE local theme files; never requested.
		$findings                = array();
		$budget                  = 40; // Files, not bytes: bounded work on huge themes.

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
				if ( preg_match_all( $cdn_hosts_to_warn_about, $contents, $m ) ) {
					$findings[] = array(
						'file'  => ltrim( str_replace( dirname( $dir ), '', $file ), '/' ),
						'hosts' => array_values( array_unique( array_map( 'strtolower', $m[0] ) ) ),
					);
				}
			}
		}

		return $findings;
	}

	/**
	 * The plugin's own asset paths, for pasting into an optimizer's
	 * "do not combine / do not defer / do not delay" field.
	 *
	 * Offered as text rather than written into anyone's settings on the
	 * owner's behalf: every optimizer changes its option schema between
	 * versions, and silently editing another plugin's configuration is not a
	 * thing a privacy plugin should do. A list the owner pastes works with
	 * optimizers this plugin has never heard of, including future ones.
	 *
	 * Paths, not URLs: that is the shape those fields want, and it stays
	 * correct on a site with a moved wp-content directory or a CDN in front.
	 *
	 * @return string[]
	 */
	public static function exclusion_paths(): array {
		$paths = array();
		foreach ( array( 'assets/js/gate.js', 'assets/js/cmp-bridge.js', 'assets/css/gate.css' ) as $asset ) {
			$path = wp_parse_url( plugins_url( $asset, CALUCON_EMBED_GATE_FILE ), PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path ) {
				$paths[] = $path;
			}
		}
		return $paths;
	}

	/**
	 * Which detected optimizers have a JS setting that costs the visitor
	 * something, and which could not be read at all.
	 *
	 * @return array[] Rows: name, state (see optimizer_state()), features.
	 */
	public static function optimizer_findings(): array {
		$readers = array(
			'WP Rocket'            => static function (): array {
				$o = get_option( 'wp_rocket_settings' );
				if ( ! is_array( $o ) ) {
					return array();
				}
				return array(
					'delay'   => ! empty( $o['delay_js'] ),
					'combine' => ! empty( $o['minify_concatenate_js'] ),
				);
			},
			'LiteSpeed Cache'      => static function (): array {
				// LiteSpeed keeps one option row per setting; js_defer is
				// 0 = off, 1 = deferred, 2 = delayed until interaction.
				$defer = get_option( 'litespeed.optm.js_defer', null );
				$comb  = get_option( 'litespeed.optm.js_comb', null );
				if ( null === $defer && null === $comb ) {
					return array();
				}
				return array(
					'delay'   => '2' === (string) $defer,
					'combine' => (bool) $comb,
				);
			},
			'Autoptimize'          => static function (): array {
				$js = get_option( 'autoptimize_js', null );
				if ( null === $js ) {
					return array();
				}
				return array(
					'delay'   => false,
					'combine' => ! empty( $js ) && ! empty( get_option( 'autoptimize_js_aggregate', true ) ),
				);
			},
			'SiteGround Optimizer' => static function (): array {
				$combine = get_option( 'siteground_optimizer_combine_javascript', null );
				if ( null === $combine ) {
					return array();
				}
				return array(
					'delay'   => false,
					'combine' => (bool) $combine,
				);
			},
		);

		$findings = array();
		foreach ( self::detect() as $row ) {
			if ( 'cache' !== $row['kind'] ) {
				continue;
			}
			$flags    = isset( $readers[ $row['name'] ] ) ? call_user_func( $readers[ $row['name'] ] ) : array();
			$state    = self::optimizer_state( $flags );
			$features = array();
			foreach ( array( 'delay', 'combine' ) as $feature ) {
				if ( ! empty( $flags[ $feature ] ) ) {
					$features[] = $feature;
				}
			}
			$findings[] = array(
				'name'     => $row['name'],
				'state'    => $state,
				'features' => $features,
			);
		}

		return $findings;
	}

	/**
	 * Turn one optimizer's read flags into a state for the screen.
	 *
	 * An empty array means the settings could not be read — a different
	 * thing from "read them, nothing risky is on", and it must never render
	 * as an all-clear. Optimizers rename and restructure their options
	 * between versions, so a false green here would be worse than saying
	 * nothing: the owner would stop looking.
	 *
	 * Pure on purpose (no get_option), so the precedence is unit-testable.
	 *
	 * @param array $flags Feature => bool, as read from that plugin.
	 * @return string 'delay', 'combine', 'off' or 'unknown'.
	 */
	public static function optimizer_state( array $flags ): string {
		if ( array() === $flags ) {
			return 'unknown';
		}
		// Delay first: it is the only one that costs the visitor a click.
		if ( ! empty( $flags['delay'] ) ) {
			return 'delay';
		}
		if ( ! empty( $flags['combine'] ) ) {
			return 'combine';
		}
		return 'off';
	}
}
