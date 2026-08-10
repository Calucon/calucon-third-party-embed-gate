<?php
/**
 * Provider registry.
 *
 * WordPress-free by design (PLAN.md §2.2). Translation and the
 * consent_gate_providers filter are injected by the integration layer.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Providers;

/**
 * Resolves an embed URL to a provider descriptor. Always resolves: an
 * unknown cross-origin iframe gets the generic fallback provider, which is
 * what makes "gate on host, not on a provider allowlist" real
 * (PLAN.md §1 invariant 6, §4.2).
 */
final class Registry {

	/** @var array[] Registered provider descriptors. */
	private array $providers;

	/** @var callable Translation function; identity outside WordPress. */
	private $translate;

	/** @var callable|null Bridge for consent_gate_provider_for_url:
	 *                     fn( array $provider, string $url, string $host ): array. */
	private $filter_provider;

	/**
	 * @param array[]       $providers       Provider descriptors (normalised on use).
	 * @param callable|null $translate       Maps an English string to the site
	 *                                       language; the integration layer passes __().
	 * @param callable|null $filter_provider Applied to every resolved descriptor,
	 *                                       builtin and generic alike, after
	 *                                       capture interpolation.
	 */
	public function __construct( array $providers = array(), ?callable $translate = null, ?callable $filter_provider = null ) {
		// Normalise once at construction (PLAN.md §9.16), not once per
		// candidate per embed.
		$this->providers       = array_map( array( Provider::class, 'normalize' ), $providers );
		$this->translate       = $translate ?? static function ( string $text ): string {
			return $text;
		};
		$this->filter_provider = $filter_provider;
	}

	/**
	 * @param array  $descriptor Resolved descriptor.
	 * @param string $url        Embed/script URL.
	 * @param string $host       Its host.
	 * @return array
	 */
	private function filtered( array $descriptor, string $url, string $host ): array {
		if ( null === $this->filter_provider ) {
			return $descriptor;
		}
		return Provider::normalize(
			(array) call_user_func( $this->filter_provider, $descriptor, $url, $host )
		);
	}

	/**
	 * Resolve a provider for an embed URL.
	 *
	 * @param string $url  Absolute embed URL (entity-decoded).
	 * @param string $host Normalised host of that URL.
	 * @return array Provider descriptor; never null (generic fallback).
	 */
	public function resolve_for_url( string $url, string $host ): array {
		foreach ( $this->providers as $descriptor ) {
			$match = $descriptor['match'];
			$hosts = isset( $match['iframe_host'] ) ? (array) $match['iframe_host'] : array();

			if ( ! in_array( $host, $hosts, true ) ) {
				continue;
			}

			if ( ! empty( $match['iframe_path'] ) ) {
				$path = (string) parse_url( $url, PHP_URL_PATH );
				if ( ! preg_match( $match['iframe_path'], $path, $m ) ) {
					continue;
				}
				$captures = array_filter( $m, 'is_string', ARRAY_FILTER_USE_KEY );
				foreach ( array( 'load_path', 'fallback', 'thumbnail' ) as $key ) {
					if ( ! empty( $descriptor[ $key ] ) ) {
						$descriptor[ $key ] = Provider::interpolate( $descriptor[ $key ], $captures );
					}
				}
			}

			return $this->filtered( $descriptor, $url, $host );
		}

		return $this->filtered( $this->generic_fallback( $url, $host ), $url, $host );
	}

	/**
	 * Resolve a provider for a third-party script src (script strategy).
	 *
	 * @param string $url  Absolute script URL (entity-decoded).
	 * @param string $host Normalised host of that URL.
	 * @return array Provider descriptor; never null (generic fallback).
	 */
	public function resolve_for_script_url( string $url, string $host ): array {
		foreach ( $this->providers as $descriptor ) {
			$hosts = isset( $descriptor['match']['script_host'] )
				? (array) $descriptor['match']['script_host'] : array();

			if ( in_array( $host, $hosts, true ) ) {
				return $this->filtered( $descriptor, $url, $host );
			}
		}

		$t = $this->translate;

		// Unknown third-party script: gated by default (invariant 6). The
		// fallback link cannot point at a .js file (PLAN.md §9.5), so it
		// points at the provider's origin — weak, but a real page.
		$generic = Provider::normalize(
			array(
				'id'       => 'generic-script',
				'label'    => $host,
				'fallback' => 'https://' . $host . '/',
				/* translators: %s: host name of the third-party script. */
				'note'     => sprintf( $t( 'Loading this content runs a script from %s, which receives your IP address and which page you are on, and may set cookies.' ), $host ),
				/* translators: %s: host name of the third-party script. */
				'action'   => sprintf( $t( 'Load content from %s' ), $host ),
				'strategy' => 'script',
			)
		);

		return $this->filtered( $generic, $url, $host );
	}

	/**
	 * The generic fallback provider for any unknown cross-origin iframe.
	 *
	 * Label is the host name; the fallback URL is the embed URL with a
	 * trailing '/embed/' stripped (the WordPress oEmbed shape), which lands
	 * on the canonical page (PLAN.md §4.2).
	 *
	 * @param string $url  Embed URL.
	 * @param string $host Host.
	 * @return array
	 */
	private function generic_fallback( string $url, string $host ): array {
		$t = $this->translate;

		return Provider::normalize(
			array(
				'id'       => 'generic',
				'label'    => $host,
				'fallback' => $this->derive_fallback_url( $url ),
				/* translators: %s: host name of the third-party embed. */
				'note'     => sprintf( $t( 'Loading this content connects your browser to %s, which receives your IP address and which page you are on, and may set cookies.' ), $host ),
				/* translators: %s: host name of the third-party embed. */
				'action'   => sprintf( $t( 'Load content from %s' ), $host ),
				'strategy' => 'iframe',
			)
		);
	}

	/**
	 * Derive a human destination from an embed URL.
	 *
	 * @param string $url Embed URL (may be protocol-relative).
	 * @return string
	 */
	private function derive_fallback_url( string $url ): string {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		$parts = parse_url( $url );
		if ( ! isset( $parts['scheme'], $parts['host'] ) ) {
			return $url;
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		// A path ending in /embed/ is the WordPress oEmbed endpoint; the
		// canonical page lives one segment up, and the ?secret= query is
		// meaningless outside the postMessage handshake. The trailing slash
		// is required: /maps/embed (Google) must keep its full URL or the
		// fallback link loses the actual map.
		if ( preg_match( '#^(.*/)embed/$#', $path, $m ) ) {
			return $parts['scheme'] . '://' . $parts['host']
				. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
				. $m[1];
		}

		return $url;
	}
}
