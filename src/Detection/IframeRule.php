<?php
/**
 * The iframe gating rule.
 *
 * WordPress-free by design (PLAN.md §2.2): filters and lifecycle actions are
 * injected as callables by the integration layer.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Detection;

use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;

/**
 * Replaces cross-origin iframes with the server-rendered placeholder.
 * Everything not replaced passes through byte-for-byte (PLAN.md §3.1) —
 * the fixture corpus asserts byte-identity on every pass-through case.
 */
final class IframeRule {

	/** @var HtmlScanner */
	private HtmlScanner $scanner;

	/** @var HostMatcher */
	private HostMatcher $hosts;

	/** @var Registry */
	private Registry $providers;

	/** @var PlaceholderRenderer */
	private PlaceholderRenderer $renderer;

	/** @var callable|null Bridge for consent_gate_should_gate: fn( bool, string $url, array $ctx ): bool */
	private $should_gate;

	/** @var callable|null Called once per gated embed: fn( array $provider, array $ctx ): void */
	private $on_gated;

	public function __construct(
		HtmlScanner $scanner,
		HostMatcher $hosts,
		Registry $providers,
		PlaceholderRenderer $renderer,
		?callable $should_gate = null,
		?callable $on_gated = null
	) {
		$this->scanner     = $scanner;
		$this->hosts       = $hosts;
		$this->providers   = $providers;
		$this->renderer    = $renderer;
		$this->should_gate = $should_gate;
		$this->on_gated    = $on_gated;
	}

	/**
	 * Gate every cross-origin iframe in a fragment.
	 *
	 * @param string $html Content HTML.
	 * @param array  $ctx  Integration context: post_id, block, integration.
	 * @return string
	 */
	public function apply( string $html, array $ctx = array() ): string {
		// Cheap probe before any parsing (PLAN.md §9.16). Placeholders
		// contain no '<iframe' substring — not even inside the payload JSON —
		// so re-feeding gated output through this rule is a no-op.
		if ( false === stripos( $html, '<iframe' ) ) {
			return $html;
		}

		$matches = $this->scanner->find_tags( $html, 'iframe' );
		if ( array() === $matches ) {
			return $html;
		}

		// Replace back-to-front so earlier offsets stay valid.
		foreach ( array_reverse( $matches ) as $match ) {
			$attributes = $match['attributes'];

			// No usable src (missing, empty, srcdoc-only, about:blank, data:)
			// means no third-party request and no honest fallback link —
			// pass through unmodified (PLAN.md §9.5).
			if ( ! isset( $attributes['src'] ) || ! is_string( $attributes['src'] ) ) {
				continue;
			}
			$src = trim( $attributes['src'] );

			if ( HostMatcher::FOREIGN !== $this->hosts->classify( $src ) ) {
				continue;
			}

			if ( null !== $this->should_gate
				&& ! call_user_func( $this->should_gate, true, $src, $ctx ) ) {
				continue;
			}

			$host = $this->hosts->host_of( $src );
			if ( null === $host ) {
				continue;
			}

			$provider = $this->providers->resolve_for_url( $src, $host );
			// The rule that matched decides the mechanics: this rule always
			// rebuilds an iframe, even for providers that also ship a script
			// variant (X/Twitter appears both ways in the field).
			$provider['strategy'] = 'iframe';

			$load_src = $this->load_src( $provider, $src );

			$span_start = $match['start'];
			$span_end   = $match['end'];

			// The WordPress embed pair (PLAN.md §9.7): consume the preceding
			// wp-embedded-content blockquote too — leaving it would duplicate
			// the panel — and harvest its link as the canonical fallback.
			$pair = $this->preceding_embed_blockquote( $html, $span_start );
			if ( null !== $pair ) {
				$span_start = $pair['start'];
				if ( '' !== $pair['href'] ) {
					$provider['fallback'] = $pair['href'];
				}
			}

			if ( '' === $provider['fallback'] ) {
				// Never a placeholder whose link points nowhere (§9.5): the
				// original embed URL is always a real page.
				$provider['fallback'] = $src;
			}

			$placeholder = $this->renderer->render( $provider, $load_src, $attributes, $ctx );

			$html = substr( $html, 0, $span_start ) . $placeholder . substr( $html, $span_end );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}

		return $html;
	}

	/**
	 * The URL the embed is loaded from AFTER consent (PLAN.md §4.1).
	 *
	 * Data minimisation, GDPR Art. 5(1)(c): prefer the provider's
	 * privacy-preserving host where one exists — measured 0 cookies on
	 * youtube-nocookie.com against 5 on the default host.
	 *
	 * @param array  $provider Normalised provider descriptor.
	 * @param string $src      Original (entity-decoded) embed URL.
	 * @return string
	 */
	private function load_src( array $provider, string $src ): string {
		if ( is_string( $provider['load_host'] ) && '' !== $provider['load_host'] ) {
			$path = ( is_string( $provider['load_path'] ) && '' !== $provider['load_path'] )
				? $provider['load_path']
				: (string) parse_url( 0 === strpos( $src, '//' ) ? 'https:' . $src : $src, PHP_URL_PATH );
			return 'https://' . $provider['load_host'] . $path;
		}

		if ( array() !== $provider['load_query'] && is_array( $provider['load_query'] ) ) {
			return $this->merge_query( $src, $provider['load_query'] );
		}

		return $src;
	}

	/**
	 * Merge query parameters into a URL, dropping autoplay flags — audio
	 * starting unbidden is not what was asked for (invariant 8).
	 *
	 * @param string $src   URL (may be protocol-relative).
	 * @param array  $extra Parameters to merge.
	 * @return string
	 */
	private function merge_query( string $src, array $extra ): string {
		$absolute = 0 === strpos( $src, '//' ) ? 'https:' . $src : $src;
		$parts    = parse_url( $absolute );
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

	/**
	 * Detect a <blockquote class="wp-embedded-content">…</blockquote>
	 * directly (whitespace allowed) before the iframe.
	 *
	 * @param string $html         Full fragment.
	 * @param int    $iframe_start Offset of the iframe's '<'.
	 * @return array{start:int,href:string}|null
	 */
	private function preceding_embed_blockquote( string $html, int $iframe_start ) {
		$before  = substr( $html, 0, $iframe_start );
		$trimmed = rtrim( $before );

		if ( ! preg_match( '/<\/blockquote\s*>$/i', $trimmed ) ) {
			return null;
		}

		$open = strripos( $trimmed, '<blockquote' );
		if ( false === $open ) {
			return null;
		}

		$blockquote_html = substr( $trimmed, $open );
		$tags            = $this->scanner->find_tags( $blockquote_html, 'blockquote' );
		if ( array() === $tags ) {
			return null;
		}

		$class = isset( $tags[0]['attributes']['class'] ) ? $tags[0]['attributes']['class'] : '';
		if ( ! is_string( $class ) || ! preg_match( '/(^|\s)wp-embedded-content(\s|$)/', $class ) ) {
			return null;
		}

		$href  = '';
		$links = $this->scanner->find_tags( $blockquote_html, 'a' );
		if ( array() !== $links && isset( $links[0]['attributes']['href'] ) && is_string( $links[0]['attributes']['href'] ) ) {
			$href = trim( $links[0]['attributes']['href'] );
		}

		return array(
			'start' => $open,
			'href'  => $href,
		);
	}
}
