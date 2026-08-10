<?php
/**
 * The script gating rule.
 *
 * Provider SDKs (Strava, X/Twitter, Instagram, TikTok, …) load as a script
 * tag next to a companion element and inject their own iframe later. The
 * script must be removed, not deferred (PLAN.md §3.5) — a deferred request
 * is still a request without consent (§9.8).
 *
 * WordPress-free by design (PLAN.md §2.2).
 *
 * @package ConsentGate
 */

namespace ConsentGate\Detection;

use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;

/**
 * Replaces third-party script tags with the placeholder panel. The companion
 * element (blockquote.twitter-tweet, div.strava-embed-placeholder, …) is
 * first-party markup and stays in place — it is the provider's own no-JS
 * fallback content, and the SDK re-renders it after consent.
 */
final class ScriptRule {

	/** @var HtmlScanner */
	private HtmlScanner $scanner;

	/** @var HostMatcher */
	private HostMatcher $hosts;

	/** @var Registry */
	private Registry $providers;

	/** @var PlaceholderRenderer */
	private PlaceholderRenderer $renderer;

	/** @var callable|null Bridge for consent_gate_should_gate. */
	private $should_gate;

	/** @var callable|null Called once per gated embed. */
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
	 * Gate every third-party script in a fragment.
	 *
	 * @param string $html Content HTML.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function apply( string $html, array $ctx = array() ): string {
		if ( false === stripos( $html, '<script' ) ) {
			return $html;
		}

		$matches = $this->scanner->find_tags( $html, 'script' );
		if ( array() === $matches ) {
			return $html;
		}

		foreach ( array_reverse( $matches ) as $match ) {
			$attributes = $match['attributes'];

			// Inline scripts have no src and cause no request; scripts the
			// site serves itself are the owner's own decision.
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

			$provider             = $this->providers->resolve_for_script_url( $src, $host );
			$provider['strategy'] = 'script';
			$provider['fallback'] = $this->resolve_fallback( $provider, $html, $match['start'], $host );

			$placeholder = $this->renderer->render( $provider, $src, array(), $ctx );

			$html = substr( $html, 0, $match['start'] ) . $placeholder . substr( $html, $match['end'] );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}

		return $html;
	}

	/**
	 * A script URL is not a human destination (§9.5). Best fallback first:
	 * the provider's companion-derived URL (Strava data attributes), then the
	 * last link inside a preceding companion blockquote (X/Twitter, Instagram
	 * and TikTok put the canonical status link there), then the descriptor's
	 * own fallback, then the script's origin.
	 *
	 * @param array  $provider     Normalised descriptor.
	 * @param string $html         Full fragment.
	 * @param int    $script_start Offset of the script tag's '<'.
	 * @param string $host         Script host.
	 * @return string
	 */
	private function resolve_fallback( array $provider, string $html, int $script_start, string $host ): string {
		$companion = $this->preceding_companion( $html, $script_start );

		if ( null !== $companion ) {
			if ( is_callable( $provider['companion_fallback'] ) ) {
				$derived = call_user_func( $provider['companion_fallback'], $companion['attributes'] );
				if ( is_string( $derived ) && '' !== $derived ) {
					return $derived;
				}
			}
			if ( '' !== $companion['last_href'] ) {
				return $companion['last_href'];
			}
		}

		if ( '' !== $provider['fallback'] ) {
			return $provider['fallback'];
		}

		return 'https://' . $host . '/';
	}

	/**
	 * The element (blockquote or div) directly before the script tag,
	 * whitespace allowed. Left in place; only read.
	 *
	 * @param string $html         Full fragment.
	 * @param int    $script_start Offset of the script tag's '<'.
	 * @return array{attributes:array,last_href:string}|null
	 */
	private function preceding_companion( string $html, int $script_start ) {
		$trimmed = rtrim( substr( $html, 0, $script_start ) );

		if ( ! preg_match( '/<\/(blockquote|div)\s*>$/i', $trimmed, $m ) ) {
			return null;
		}
		$tag = strtolower( $m[1] );

		$open = strripos( $trimmed, '<' . $tag );
		if ( false === $open ) {
			return null;
		}

		$companion_html = substr( $trimmed, $open );
		$tags           = $this->scanner->find_tags( $companion_html, $tag );
		if ( array() === $tags || 0 !== $tags[0]['start'] ) {
			return null;
		}

		$last_href = '';
		foreach ( $this->scanner->find_tags( $companion_html, 'a' ) as $link ) {
			if ( isset( $link['attributes']['href'] ) && is_string( $link['attributes']['href'] ) ) {
				$href = trim( $link['attributes']['href'] );
				if ( '' !== $href && preg_match( '#^(https?:)?//#i', $href ) ) {
					$last_href = $href;
				}
			}
		}

		return array(
			'attributes' => $tags[0]['attributes'],
			'last_href'  => $last_href,
		);
	}
}
