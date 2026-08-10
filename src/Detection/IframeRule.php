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

use ConsentGate\Providers\LoadUrl;
use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;

/**
 * Replaces cross-origin iframes with the server-rendered placeholder.
 * Everything not replaced passes through byte-for-byte (PLAN.md §3.1) —
 * the fixture corpus asserts byte-identity on every pass-through case.
 */
final class IframeRule {

	/**
	 * Attributes lazy-load plugins park the real URL in while leaving src
	 * empty, about:blank or a data: shim. Deferred is still without consent
	 * (§9.8) — the parked URL is the one that fires on scroll.
	 */
	private const LAZY_SRC_ATTRIBUTES = array( 'data-src', 'data-lazy-src', 'data-original' );

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

		// Drop matches nested inside an earlier match's span. With mis-nested
		// or unclosed iframes the first-close heuristic makes spans overlap,
		// and splicing both corrupts the page: the outer replacement cuts
		// through the inner placeholder and leaks raw attribute fragments as
		// visible text. The outer iframe is the one browsers render; anything
		// inside its span is fallback content and is consumed with it.
		$kept     = array();
		$last_end = -1;
		foreach ( $matches as $match ) {
			if ( $match['start'] < $last_end ) {
				continue;
			}
			$kept[]   = $match;
			$last_end = $match['end'];
		}

		// Replace back-to-front so earlier offsets stay valid.
		foreach ( array_reverse( $kept ) as $match ) {
			$attributes = $match['attributes'];

			$src = $this->effective_src( $attributes );

			if ( null === $src ) {
				$srcdoc = $this->srcdoc_target( $attributes );
				if ( null === $srcdoc ) {
					// No usable URL anywhere (missing, empty, about:blank,
					// data:, relative, inert srcdoc) means no third-party
					// request and no honest fallback link — pass through
					// unmodified (PLAN.md §9.5).
					continue;
				}
				$html = $this->gate_srcdoc( $html, $match, $srcdoc, $ctx );
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
			if ( false === $provider['enabled'] ) {
				// The owner explicitly exempted this provider; their call.
				continue;
			}

			// A foreign iframe the visitor cannot see is a tracking pixel,
			// not content: there is nothing to offer a panel for and no page
			// worth a fallback link. Remove it entirely — a GTM noscript
			// pixel must not become a visible "Load content from
			// googletagmanager.com" panel for no-JS visitors.
			if ( $this->is_invisible( $attributes ) ) {
				$html = substr( $html, 0, $match['start'] ) . substr( $html, $match['end'] );
				continue;
			}

			// The rule that matched decides the mechanics: this rule always
			// rebuilds an iframe, even for providers that also ship a script
			// variant (X/Twitter appears both ways in the field).
			$provider['strategy'] = 'iframe';

			$load_src = LoadUrl::for_provider( $provider, $src );

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
	 * The URL this iframe will actually request: a foreign src, or the URL a
	 * lazy-load plugin parked in a data attribute while src is a shim.
	 *
	 * @param array $attributes Lowercased, decoded attributes.
	 * @return string|null Null when nothing foreign would be requested.
	 */
	private function effective_src( array $attributes ) {
		if ( isset( $attributes['src'] ) && is_string( $attributes['src'] ) ) {
			$src = trim( $attributes['src'] );
			if ( HostMatcher::FOREIGN === $this->hosts->classify( $src ) ) {
				return $src;
			}
		}

		foreach ( self::LAZY_SRC_ATTRIBUTES as $name ) {
			if ( isset( $attributes[ $name ] ) && is_string( $attributes[ $name ] ) ) {
				$src = trim( $attributes[ $name ] );
				if ( HostMatcher::FOREIGN === $this->hosts->classify( $src ) ) {
					return $src;
				}
			}
		}

		return null;
	}

	/**
	 * Whether the iframe is invisible to the visitor: zero-sized, hidden, or
	 * display:none. Matches the shapes trackers actually use (GTM's noscript
	 * pixel is height=0 width=0 style="display:none;visibility:hidden").
	 * Deliberately NOT matched: visibility:hidden alone — core's own
	 * WordPress-to-WordPress embed iframe ships hidden that way until
	 * wp-embed.js reveals it, and it is real content.
	 *
	 * @param array $attributes Lowercased, decoded attributes.
	 * @return bool
	 */
	private function is_invisible( array $attributes ): bool {
		if ( array_key_exists( 'hidden', $attributes ) ) {
			return true;
		}
		$width  = isset( $attributes['width'] ) ? trim( (string) $attributes['width'] ) : null;
		$height = isset( $attributes['height'] ) ? trim( (string) $attributes['height'] ) : null;
		if ( in_array( $width, array( '0', '1' ), true ) && in_array( $height, array( '0', '1' ), true ) ) {
			return true;
		}
		$style = isset( $attributes['style'] ) && is_string( $attributes['style'] ) ? $attributes['style'] : '';
		return (bool) preg_match( '/display\s*:\s*none/i', $style );
	}

	/**
	 * A srcdoc iframe with no usable src still executes its inline document —
	 * including third-party <script src> and <img src> (the widely copied
	 * "srcdoc lazy YouTube" snippet requests the thumbnail at page load). The
	 * §9.5 pass-through only holds when the srcdoc references nothing foreign.
	 *
	 * @param array $attributes Lowercased, decoded attributes.
	 * @return array{url:string,fallback:string}|null Null when nothing foreign
	 *         is referenced.
	 */
	private function srcdoc_target( array $attributes ) {
		if ( ! isset( $attributes['srcdoc'] ) || ! is_string( $attributes['srcdoc'] ) || '' === trim( $attributes['srcdoc'] ) ) {
			return null;
		}
		$doc = $attributes['srcdoc'];

		if ( ! preg_match_all( '~(?:https?:)?//[^\s"\'<>]+~i', $doc, $m ) ) {
			return null;
		}
		$first = null;
		foreach ( $m[0] as $url ) {
			if ( HostMatcher::FOREIGN === $this->hosts->classify( $url ) ) {
				$first = $url;
				break;
			}
		}
		if ( null === $first ) {
			return null;
		}

		// Prefer a real link inside the srcdoc as the fallback destination —
		// the lazy-YouTube snippet wraps its thumbnail in the watch URL.
		$fallback = $first;
		foreach ( $this->scanner->find_tags( $doc, 'a' ) as $link ) {
			if ( isset( $link['attributes']['href'] ) && is_string( $link['attributes']['href'] )
				&& HostMatcher::FOREIGN === $this->hosts->classify( trim( $link['attributes']['href'] ) ) ) {
				$fallback = trim( $link['attributes']['href'] );
				break;
			}
		}

		return array(
			'url'      => $first,
			'fallback' => 0 === strpos( $fallback, '//' ) ? 'https:' . $fallback : $fallback,
		);
	}

	/**
	 * Gate a srcdoc iframe: the placeholder's payload carries the original
	 * srcdoc verbatim, restored only on click — equal privilege, never wider
	 * (invariant 7).
	 *
	 * @param string $html      Full fragment.
	 * @param array  $tag_match Scanner match.
	 * @param array  $target    From srcdoc_target().
	 * @param array  $ctx       Integration context.
	 * @return string
	 */
	private function gate_srcdoc( string $html, array $tag_match, array $target, array $ctx ): string {
		if ( null !== $this->should_gate
			&& ! call_user_func( $this->should_gate, true, $target['url'], $ctx ) ) {
			return $html;
		}

		$host = $this->hosts->host_of( $target['url'] );
		if ( null === $host ) {
			return $html;
		}

		$provider = $this->providers->resolve_for_url( $target['url'], $host );
		if ( false === $provider['enabled'] ) {
			return $html;
		}
		$provider['strategy'] = 'iframe';
		$provider['fallback'] = $target['fallback'];

		$placeholder = $this->renderer->render( $provider, '', $tag_match['attributes'], $ctx );

		$html = substr( $html, 0, $tag_match['start'] ) . $placeholder . substr( $html, $tag_match['end'] );

		if ( null !== $this->on_gated ) {
			call_user_func( $this->on_gated, $provider, $ctx );
		}

		return $html;
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
