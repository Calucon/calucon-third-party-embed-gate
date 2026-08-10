<?php
/**
 * CMP detection for the §6.4 bridge — which consent platform is installed,
 * and whether it is on the tested, interoperable list.
 *
 * Detection is by constants/classes/functions the platforms themselves
 * define — the same technique as Admin\Compatibility, which consumes this
 * class for its CMP rows. The bridge is offered ONLY for platforms on the
 * tested list; an unknown or untested platform keeps the fail-closed
 * default (gating stands regardless of its choices).
 *
 * @package ConsentGate
 */

namespace ConsentGate\Cmp;

/**
 * Which CMP is present, by its own runtime markers.
 */
final class Detector {

	/**
	 * The tested, interoperable platforms — the §6.4 curated list. Order is
	 * bridge priority: when several markers are present at once (e.g. a CMP
	 * plus the WP Consent API plugin it registers with), the FIRST detected
	 * entry supplies the adapter. Native adapters outrank the generic
	 * WP Consent API one because they carry per-service state and the
	 * platform's own regional semantics.
	 *
	 * Each row: adapter id (shared with assets/js/cmp-bridge.js), label for
	 * the admin, and a detect callable kept lazy so class_exists/defined
	 * probes run only when asked.
	 *
	 * @return array[] Rows: id, label, detect (callable): bool.
	 */
	public static function bridgeable(): array {
		return array(
			array(
				'id'     => 'complianz',
				'label'  => 'Complianz',
				'detect' => static function (): bool {
					return defined( 'cmplz_version' ) || function_exists( 'cmplz_has_consent' );
				},
			),
			array(
				'id'     => 'cookiebot',
				'label'  => 'Cookiebot',
				'detect' => static function (): bool {
					return class_exists( 'Cookiebot_WP' ) || defined( 'CYBOT_COOKIEBOT_PLUGIN_VERSION' );
				},
			),
			array(
				'id'     => 'cookieyes',
				'label'  => 'CookieYes',
				'detect' => static function (): bool {
					return defined( 'CLI_SETTINGS_FIELD' ) || defined( 'COOKIEYES_PLUGIN_FILENAME' );
				},
			),
			array(
				'id'     => 'borlabs',
				'label'  => 'Borlabs Cookie',
				'detect' => static function (): bool {
					return defined( 'BORLABS_COOKIE_VERSION' ) || class_exists( '\\Borlabs\\Cookie\\Plugin' ) || class_exists( '\\BorlabsCookie\\Cookie\\Frontend\\Frontend' );
				},
			),
			array(
				'id'     => 'real-cookie-banner',
				'label'  => 'Real Cookie Banner',
				'detect' => static function (): bool {
					return defined( 'RCB_FILE' ) || class_exists( '\\DevOwl\\RealCookieBanner\\Core' );
				},
			),
			// Generic last: any platform that registers with the WP Consent
			// API plugin (Complianz, Cookiebot, CookieYes, iubenda, Moove …)
			// speaks this contract; it is the WordPress-native abstraction.
			array(
				'id'     => 'wp-consent-api',
				'label'  => 'WP Consent API',
				'detect' => static function (): bool {
					return function_exists( 'wp_has_consent' ) || class_exists( 'WP_CONSENT_API' );
				},
			),
		);
	}

	/**
	 * Every detected platform from the tested list, in priority order.
	 *
	 * @return array[] Rows: id, label.
	 */
	public static function detected(): array {
		$found = array();
		foreach ( self::bridgeable() as $row ) {
			if ( call_user_func( $row['detect'] ) ) {
				$found[] = array(
					'id'    => $row['id'],
					'label' => $row['label'],
				);
			}
		}
		return $found;
	}

	/**
	 * Detected consent platforms that are NOT on the tested list — shown on
	 * the Compatibility screen with the fail-closed message. Usercentrics'
	 * standalone loader is the known case: its service identifiers are
	 * site-specific, so no generic bridge can be tested against it.
	 *
	 * @return array[] Rows: id, label.
	 */
	public static function detected_untested(): array {
		$found = array();
		$cmps  = array(
			array(
				'id'     => 'usercentrics',
				'label'  => 'Usercentrics',
				'detect' => static function (): bool {
					return defined( 'UC_PLUGIN_FILE' ) || class_exists( '\\Usercentrics\\Plugin' );
				},
			),
			array(
				'id'     => 'iubenda',
				'label'  => 'iubenda',
				'detect' => static function (): bool {
					return defined( 'IUB_PLUGIN_VERSION' ) || class_exists( 'iubendaParser' );
				},
			),
		);
		foreach ( $cmps as $row ) {
			if ( call_user_func( $row['detect'] ) ) {
				$found[] = array(
					'id'    => $row['id'],
					'label' => $row['label'],
				);
			}
		}
		return $found;
	}
}
