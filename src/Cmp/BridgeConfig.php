<?php
/**
 * Bridge configuration (§6.4) — the pure decision of WHAT the front-end
 * bridge script gets to work with.
 *
 * WordPress-free by design (like Detection/ and Rendering/): detected
 * platforms and sanitised options in, a plain config array (or null) out.
 * All fail-closed rules live here where the unit suite can pin them:
 *
 * - Bridge off in the options → null, regardless of what is installed.
 * - Nothing detected from the tested list → null: an untested platform
 *   gets no adapter, so gating stands (the §6.4 fail-closed default).
 * - One adapter only, highest priority first: consent state must have a
 *   single authority; merging several platforms' verdicts would let the
 *   most permissive one win.
 * - TCF rides along only when its own flag is set AND at least one
 *   provider declares a TCF vendor id — a vendor-less TCF bridge could
 *   never grant anything and would only add dead code to the page.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Cmp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the caluconEmbedGateConfig.cmp payload.
 */
final class BridgeConfig {

	/**
	 * Consent categories the adapters ask their platform about. YouTube-style
	 * embedded content is conventionally "marketing" (the WP Consent API's
	 * own service fallback category); CookieYes names the same bucket
	 * "advertisement". Borlabs groups are site-defined, so that one is an
	 * option, not a constant.
	 */
	private const CATEGORY_DEFAULT   = 'marketing';
	private const CATEGORY_COOKIEYES = 'advertisement';

	/**
	 * TCF Global Vendor List ids for built-in providers that are registered
	 * there. Deliberately sparse: most embed providers (Vimeo, OSM, …) are
	 * not GVL vendors at all — for those TCF simply has no answer and the
	 * click remains the only signal (fail closed). 755 = Google Advertising
	 * Products, the GVL entry covering YouTube and Google Maps.
	 */
	private const TCF_VENDORS = array(
		'youtube'     => 755,
		'google-maps' => 755,
	);

	/**
	 * @param array[] $detected    Detector::detected() rows (id, label), priority order.
	 * @param array   $cmp_options Sanitised options['cmp'] tree.
	 * @return array|null Config for the bridge script, null when the bridge stays off.
	 */
	public static function build( array $detected, array $cmp_options ): ?array {
		$enabled = isset( $cmp_options['bridge'] ) && true === $cmp_options['bridge'];
		if ( ! $enabled ) {
			return null;
		}

		$adapter = null;
		foreach ( $detected as $row ) {
			if ( isset( $row['id'] ) && is_string( $row['id'] ) && '' !== $row['id'] ) {
				$adapter = $row['id'];
				break;
			}
		}

		$tcf = self::tcf_config( $cmp_options );

		if ( null === $adapter && null === $tcf ) {
			return null;
		}

		$config = array(
			'adapter'  => $adapter,
			'category' => 'cookieyes' === $adapter ? self::CATEGORY_COOKIEYES : self::CATEGORY_DEFAULT,
		);

		if ( 'borlabs' === $adapter ) {
			$group                  = isset( $cmp_options['borlabs_group'] ) && is_string( $cmp_options['borlabs_group'] ) ? $cmp_options['borlabs_group'] : '';
			$config['borlabsGroup'] = '' !== $group ? $group : 'external-media';
		}

		if ( null !== $tcf ) {
			$config['tcf'] = $tcf;
		}

		return $config;
	}

	/**
	 * @param array $cmp_options Sanitised options['cmp'] tree.
	 * @return array|null TCF sub-config, null when the flag is off.
	 */
	private static function tcf_config( array $cmp_options ): ?array {
		if ( ! isset( $cmp_options['tcf'] ) || true !== $cmp_options['tcf'] ) {
			return null;
		}
		return array( 'vendors' => self::TCF_VENDORS );
	}
}
