<?php
/**
 * Post-consent load URL construction.
 *
 * WordPress-free by design (PLAN.md §2.2). Extracted from IframeRule so the
 * embed/object rule shares one implementation of the privacy-preserving
 * rewrite instead of drifting its own copy.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the URL an embed is loaded from AFTER consent (PLAN.md §4.1).
 */
final class LoadUrl {

	/**
	 * Data minimisation, GDPR Art. 5(1)(c): prefer the provider's
	 * privacy-preserving host where one exists — measured 0 cookies on
	 * youtube-nocookie.com against 5 on the default host.
	 *
	 * @param array  $provider Normalised provider descriptor.
	 * @param string $src      Original (entity-decoded) embed URL.
	 * @return string
	 */
	public static function for_provider( array $provider, string $src ): string {
		if ( is_string( $provider['load_host'] ) && '' !== $provider['load_host'] ) {
			$path = ( is_string( $provider['load_path'] ) && '' !== $provider['load_path'] )
				? $provider['load_path']
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress-free layer (PLAN.md §2.2); wp_parse_url() is unavailable in the no-WordPress fixture suite.
				: (string) parse_url( 0 === strpos( $src, '//' ) ? 'https:' . $src : $src, PHP_URL_PATH );
			// The embed's own parameters come along. Rebuilding from the path
			// alone dropped all of them: a playlist (`/embed/videoseries
			// ?list=…`) loaded as an error page, and a start time, captions,
			// `rel=0` or the JS API's `enablejsapi` were silently gone after
			// the click (until 1.0.2).
			$query = self::carried_query( $src );
			return 'https://' . $provider['load_host'] . $path . ( '' !== $query ? '?' . $query : '' );
		}

		if ( array() !== $provider['load_query'] && is_array( $provider['load_query'] ) ) {
			return self::merge_query( $src, $provider['load_query'] );
		}

		return $src;
	}

	/**
	 * The query string an embed URL carries over to its privacy-preserving
	 * host: everything the owner (or their embed code) set, minus two kinds
	 * of parameter. Autoplay flags, as everywhere (invariant 8). And the
	 * attribution markers that say where an embed code came from —
	 * `feature=oembed`, the `si` share id — which change nothing about
	 * playback and are one more thing told to the provider.
	 *
	 * @param string $src Original embed URL (may be protocol-relative).
	 * @return string Query string without the leading '?', '' when none.
	 */
	private static function carried_query( string $src ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress-free layer (PLAN.md §2.2); wp_parse_url() is unavailable in the no-WordPress fixture suite.
		$query = parse_url( 0 === strpos( $src, '//' ) ? 'https:' . $src : $src, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return '';
		}
		$params = array();
		parse_str( $query, $params );
		unset( $params['autoplay'], $params['auto_play'], $params['feature'], $params['si'] );

		return http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Merge query parameters into a URL, dropping autoplay flags — audio
	 * starting unbidden is not what was asked for (invariant 8).
	 *
	 * @param string $src   URL (may be protocol-relative).
	 * @param array  $extra Parameters to merge.
	 * @return string
	 */
	private static function merge_query( string $src, array $extra ): string {
		$absolute = 0 === strpos( $src, '//' ) ? 'https:' . $src : $src;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress-free layer (PLAN.md §2.2); wp_parse_url() is unavailable in the no-WordPress fixture suite.
		$parts = parse_url( $absolute );
		if ( ! isset( $parts['scheme'], $parts['host'] ) ) {
			return $src;
		}

		$params = array();
		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $params );
		}
		unset( $params['autoplay'], $params['auto_play'] );
		$params = array_merge( $params, $extra );

		return $parts['scheme'] . '://' . $parts['host']
			. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
			. ( isset( $parts['path'] ) ? $parts['path'] : '' )
			. ( array() !== $params ? '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) : '' )
			. ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
	}
}
