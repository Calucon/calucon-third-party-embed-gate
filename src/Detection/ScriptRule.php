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
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Rendering\PlaceholderRenderer;

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

	/** @var callable|null Bridge for calucon_embed_gate_should_gate. */
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

		$inline = array();
		foreach ( array_reverse( $matches ) as $match ) {
			$attributes = $match['attributes'];

			// Inline scripts cause no request by themselves — unless they
			// inject a known provider's loader (Scribd, Crowdsignal surveys).
			// Those are handled in a second pass, after every external script
			// of the same provider has its panel, so the inline loader can
			// attach to it silently. Scripts the site serves itself are the
			// owner's own decision.
			if ( ! isset( $attributes['src'] ) || ! is_string( $attributes['src'] ) ) {
				$inline[] = $match;
				continue;
			}
			$src = trim( $attributes['src'] );

			if ( HostMatcher::FOREIGN !== $this->hosts->classify( $src ) ) {
				continue;
			}

			// A CDN that rewrites the finished HTML makes the site's own
			// scripts look third-party; gating those breaks the site instead
			// of protecting anyone. Scripts and stylesheets only — see
			// HostMatcher::looks_like_own_asset_path().
			//
			// But the path is a heuristic about shape, and shape is something
			// anyone can copy: without this second condition, any host at all
			// could serve a tracker from /wp-content/ and be waved through,
			// which is the invisible failure invariant 6 exists to forbid. A
			// host we already know to be a provider is never the site's own
			// asset host, so the exemption does not apply to it.
			//
			// Deliberately BEFORE the force_gate check below. The two answer
			// different questions: force_gate is the owner saying "gate the
			// embeds in this block", while this says "that is not a
			// third-party embed at all, it is one of my own files". A block
			// marked always-gate should not start placeholdering the site's
			// own scripts. The owner's host-level always-gate list is the
			// setting that does override this, and it is honoured inside
			// is_exempt_own_asset().
			if ( $this->hosts->is_exempt_own_asset( $src )
				&& ! $this->is_known_provider_host( $src ) ) {
				continue;
			}

			if ( empty( $ctx['force_gate'] ) && null !== $this->should_gate
				&& ! call_user_func( $this->should_gate, true, $src, $ctx ) ) {
				continue;
			}

			$host = $this->hosts->host_of( $src );
			if ( null === $host ) {
				continue;
			}

			$provider = $this->providers->resolve_for_script_url( $src, $host );
			if ( empty( $ctx['force_gate'] ) && false === $provider['enabled'] ) {
				continue;
			}
			// A loader script that accompanies an iframe of the SAME provider
			// (VideoPress pastes one after its player) is part of that embed,
			// not a second one: gate it silently — no panel of its own — and
			// gate.js injects it once the visible panel is activated. Only
			// when that panel exists in this fragment (the iframe rule ran
			// first); a lone loader keeps its own panel and fallback link.
			$silent = 'iframe' === $provider['strategy']
				&& false !== strpos( $html, 'data-cg-provider="' . $provider['id'] . '"' )
				&& $this->has_adjacent_panel( $html, $provider['id'], $match['start'], $match['end'] );

			$provider['strategy'] = 'script';
			$provider['fallback'] = $this->resolve_fallback( $provider, $html, $match['start'], $match['end'], $host );

			$placeholder = $this->renderer->render( $provider, $src, array(), $silent ? $ctx + array( 'silent' => true ) : $ctx );

			$html = substr( $html, 0, $match['start'] ) . $placeholder . substr( $html, $match['end'] );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}

		return $this->apply_inline( $html, $inline, $ctx );
	}

	/**
	 * Is there a visible panel for this provider NEXT TO this script?
	 *
	 * "Somewhere in the fragment" is not good enough. A post with two embeds
	 * from one provider — a Crowdsignal poll and a Crowdsignal survey — has a
	 * panel for the first, and the second's loader would then be silenced by
	 * it: no panel, no fallback link, and it loads on the other embed's click.
	 * One consent standing for two embeds, and a no-JS visitor left with no
	 * link at all (invariant 2).
	 *
	 * Adjacency is structural, not a character count: the panel and the script
	 * must sit in the same block, with no block-level tag and no blank line
	 * between them. WordPress wraps each embed in its own <figure>, so the
	 * boundary between two embeds is always crossed.
	 *
	 * Silent spans are skipped — a companion cannot vouch for a companion —
	 * and anything unrecognised fails towards a panel of its own, which is the
	 * safe direction: a visible extra panel, never a silenced embed.
	 *
	 * @param string $html         Fragment being rewritten.
	 * @param string $provider_id  Provider whose panel to look for.
	 * @param int    $script_start Start offset of the script tag.
	 * @param int    $script_end   Offset just past the script tag.
	 * @return bool
	 */
	private function has_adjacent_panel( string $html, string $provider_id, int $script_start, int $script_end ): bool {
		foreach ( $this->scanner->find_tags( $html, 'div' ) as $tag ) {
			$attributes = $tag['attributes'];
			if ( ! isset( $attributes['data-cg-provider'] ) || $attributes['data-cg-provider'] !== $provider_id ) {
				continue;
			}
			$class = isset( $attributes['class'] ) ? (string) $attributes['class'] : '';
			if ( false === strpos( $class, 'cg-embed' ) || false !== strpos( $class, 'cg-embed--silent' ) ) {
				continue;
			}

			if ( $tag['end'] <= $script_start ) {
				$gap = substr( $html, $tag['end'], $script_start - $tag['end'] );
			} elseif ( $tag['start'] >= $script_end ) {
				$gap = substr( $html, $script_end, $tag['start'] - $script_end );
			} else {
				continue;
			}

			if ( ! self::block_boundary_between( $gap ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Does this text separate two blocks? Any block-level tag, or the blank
	 * line that separates block-level content in classic (unwrapped) posts.
	 *
	 * @param string $gap Markup between a panel and a script.
	 * @return bool
	 */
	private static function block_boundary_between( string $gap ): bool {
		return 1 === preg_match( '#</?(?:figure|p|section|article|aside|main|h[1-6]|hr|table|ul|ol|blockquote)\b#i', $gap )
			|| 1 === preg_match( '/\n\s*\n/', $gap );
	}

	/**
	 * Does the provider host appear in this code as a STRING — the shape of a
	 * URL something is about to fetch — rather than only in a comment?
	 *
	 * The injection probe alone ("this script calls createElement, and names a
	 * provider host somewhere") is too generous: a site's own script that
	 * assigns any `.src` and mentions a provider URL in a comment matched it,
	 * and matching means the script is REMOVED and replaced by a "Load …"
	 * panel — the site's own code silently stops running. Requiring the host
	 * to sit inside a string literal separates "loads this" from "talks about
	 * this" without narrowing which injection shapes count: a loader built any
	 * of the four ways still carries its URL in a string.
	 *
	 * Tightened deliberately on WHERE the host appears, never on HOW it is
	 * injected: the failure mode of a narrower injection list is a provider
	 * loader running before the click (invariant 1), which is far worse than
	 * a false positive.
	 *
	 * @param string $code Inline script body.
	 * @param string $host Provider host the resolver matched.
	 * @return bool
	 */
	private static function host_in_string_literal( string $code, string $host ): bool {
		$needle = '//' . strtolower( $host ) . '/';
		$size   = strlen( $needle );
		$lower  = strtolower( $code );
		$length = strlen( $lower );
		$quote  = '';
		$i      = 0;

		while ( $i < $length ) {
			$char = $lower[ $i ];

			if ( '' !== $quote ) {
				if ( '\\' === $char ) {
					$i += 2;
					continue;
				}
				if ( $char === $quote ) {
					$quote = '';
					++$i;
					continue;
				}
				if ( $i + $size <= $length && 0 === substr_compare( $lower, $needle, $i, $size ) ) {
					return true;
				}
				++$i;
				continue;
			}

			// Outside a string, skip comments whole so a URL written in one
			// never counts. A bare URL outside a string is not code anyone
			// fetches either way, so reading `//` as a comment start here is
			// safe.
			if ( '/' === $char && $i + 1 < $length ) {
				if ( '/' === $lower[ $i + 1 ] ) {
					$end = strpos( $lower, "\n", $i );
					$i   = false === $end ? $length : $end + 1;
					continue;
				}
				if ( '*' === $lower[ $i + 1 ] ) {
					$end = strpos( $lower, '*/', $i + 2 );
					$i   = false === $end ? $length : $end + 2;
					continue;
				}
			}

			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$quote = $char;
			}
			++$i;
		}

		return false;
	}

	/**
	 * Second pass: inline scripts that inject a known provider's loader.
	 * The offsets still hold because the first pass replaced later matches
	 * first and we walk these from the end as well — but an earlier
	 * replacement could have shifted nothing before it, so re-scan to be
	 * safe: the scanner is cheap and the list is short.
	 *
	 * @param string  $html    Fragment after the external-script pass.
	 * @param array[] $pending Inline matches from the first pass (ignored; re-scanned).
	 * @param array   $ctx     Integration context.
	 * @return string
	 */
	private function apply_inline( string $html, array $pending, array $ctx ): string {
		if ( array() === $pending || false === stripos( $html, '<script' ) ) {
			return $html;
		}
		foreach ( array_reverse( $this->scanner->find_tags( $html, 'script' ) ) as $match ) {
			if ( isset( $match['attributes']['src'] ) ) {
				continue;
			}
			$span = substr( $html, $match['start'], $match['end'] - $match['start'] );
			if ( ! preg_match( '#^<script\b[^>]*>(.*)</script\s*>$#is', $span, $m ) ) {
				continue;
			}
			$code = $m[1];
			if ( '' === trim( $code ) ) {
				continue;
			}
			$provider = $this->providers->resolve_for_inline_script( $code );
			if ( null === $provider ) {
				continue;
			}
			if ( empty( $ctx['force_gate'] ) && false === $provider['enabled'] ) {
				continue;
			}
			$host = '';
			foreach ( (array) $provider['match']['script_host'] as $candidate ) {
				if ( false !== stripos( $code, '//' . $candidate . '/' ) ) {
					$host = (string) $candidate;
					break;
				}
			}
			if ( empty( $ctx['force_gate'] ) && null !== $this->should_gate
				&& ! call_user_func( $this->should_gate, true, 'https://' . $host . '/', $ctx ) ) {
				continue;
			}

			// Two very different inline scripts name a provider host:
			//
			//  - one that INJECTS the provider's loader (Scribd's, and the
			//    Crowdsignal survey bootstrap) — a request on load, so it is
			//    gated wherever it appears, with its own panel if it is the
			//    only thing standing for that embed;
			//  - one that merely CALLS into an already-gated script (Wolfram's
			//    embed() line) or just mentions a URL — no request by itself.
			//    Gating that is only right as a silent companion of a panel
			//    that already exists; a site's own script that happens to name
			//    a provider URL must keep running, and must never sprout a
			//    "Load content from …" panel of its own.
			$silent  = false !== strpos( $html, 'data-cg-provider="' . $provider['id'] . '"' )
				&& $this->has_adjacent_panel( $html, $provider['id'], $match['start'], $match['end'] );
			$injects = 1 === preg_match( '/createElement|\.src\s*=|document\.write|insertAdjacentHTML/i', $code )
				&& self::host_in_string_literal( $code, $host );
			if ( ! $injects && ! $silent ) {
				continue;
			}

			$provider['strategy'] = 'script';
			$provider['fallback'] = $this->resolve_fallback( $provider, $html, $match['start'], $match['end'], $host );

			$placeholder = $this->renderer->render(
				$provider,
				'https://' . $host . '/',
				array(),
				$silent ? $ctx + array( 'silent' => true ) : $ctx,
				array( 'inline' => $code )
			);

			$html = substr( $html, 0, $match['start'] ) . $placeholder . substr( $html, $match['end'] );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}
		return $html;
	}

	/**
	 * A script URL is not a human destination (§9.5). Best fallback first:
	 * the provider's companion-derived URL (Strava data attributes, Facebook
	 * data-href), then the last link inside the provider's OWN companion
	 * element (X/Twitter, Instagram and TikTok put the canonical status link
	 * there), then the descriptor's own fallback, then the script's origin.
	 *
	 * Companion links are harvested only from an element matching the
	 * provider's declared companion_class — never from whatever element
	 * happens to sit next to the script, which turned a nav's last link into
	 * an "Open on …" destination. The companion may precede the script
	 * (Twitter/Instagram) or follow it (Facebook's fb-root/script/fb-post
	 * shape), so both neighbours are checked.
	 *
	 * @param array  $provider     Normalised descriptor.
	 * @param string $html         Full fragment.
	 * @param int    $script_start Offset of the script tag's '<'.
	 * @param int    $script_end   Offset just past the script tag's span.
	 * @param string $host         Script host.
	 * @return string
	 */
	private function resolve_fallback( array $provider, string $html, int $script_start, int $script_end, string $host ): string {
		$companion = $this->companion_for( $provider, $html, $script_start, $script_end );

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
	 * The provider's companion element next to the script, or null.
	 *
	 * @param array  $provider     Normalised descriptor.
	 * @param string $html         Full fragment.
	 * @param int    $script_start Offset of the script tag's '<'.
	 * @param int    $script_end   Offset just past the script tag's span.
	 * @return array{attributes:array,last_href:string}|null
	 */
	private function companion_for( array $provider, string $html, int $script_start, int $script_end ) {
		$classes = is_array( $provider['companion_class'] ) ? $provider['companion_class'] : array();
		if ( array() === $classes ) {
			return null;
		}

		$before = $this->preceding_companion( $html, $script_start );
		if ( null !== $before && $this->has_any_class( $before['attributes'], $classes ) ) {
			return $before;
		}

		$after = $this->following_companion( $html, $script_end );
		if ( null !== $after && $this->has_any_class( $after['attributes'], $classes ) ) {
			return $after;
		}

		return null;
	}

	/**
	 * @param array    $attributes Element attributes.
	 * @param string[] $classes    Class names to look for.
	 * @return bool
	 */
	private function has_any_class( array $attributes, array $classes ): bool {
		$class = isset( $attributes['class'] ) && is_string( $attributes['class'] ) ? $attributes['class'] : '';
		if ( '' === $class ) {
			return false;
		}
		$present = preg_split( '/\s+/', trim( $class ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $classes as $wanted ) {
			if ( in_array( $wanted, $present, true ) ) {
				return true;
			}
		}
		return false;
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

		return array(
			'attributes' => $tags[0]['attributes'],
			'last_href'  => $this->last_absolute_href( $companion_html ),
		);
	}

	/**
	 * The element (blockquote or div) directly after the script tag,
	 * whitespace allowed. Left in place; only read.
	 *
	 * @param string $html       Full fragment.
	 * @param int    $script_end Offset just past the script tag's span.
	 * @return array{attributes:array,last_href:string}|null
	 */
	private function following_companion( string $html, int $script_end ) {
		$rest = ltrim( substr( $html, $script_end ) );

		if ( ! preg_match( '/^<(blockquote|div)(?=[\s\/>])/i', $rest, $m ) ) {
			return null;
		}
		$tag = strtolower( $m[1] );

		$tags = $this->scanner->find_tags( $rest, $tag );
		if ( array() === $tags || 0 !== $tags[0]['start'] ) {
			return null;
		}

		$companion_html = substr( $rest, 0, $tags[0]['end'] );

		return array(
			'attributes' => $tags[0]['attributes'],
			'last_href'  => $this->last_absolute_href( $companion_html ),
		);
	}

	/**
	 * @param string $companion_html Companion element markup.
	 * @return string Last absolute link inside it, '' when none.
	 */
	private function last_absolute_href( string $companion_html ): string {
		$last_href = '';
		foreach ( $this->scanner->find_tags( $companion_html, 'a' ) as $link ) {
			if ( isset( $link['attributes']['href'] ) && is_string( $link['attributes']['href'] ) ) {
				$href = trim( $link['attributes']['href'] );
				if ( '' !== $href && preg_match( '#^(https?:)?//#i', $href ) ) {
					$last_href = $href;
				}
			}
		}
		return $last_href;
	}

	/**
	 * Does this URL sit on a host a registered provider owns?
	 *
	 * Used only to refuse the own-asset path exemption. A CDN hostname is
	 * never a provider, so the exemption keeps working for the case it was
	 * added for.
	 *
	 * @param string $url Raw URL from the markup.
	 * @return bool
	 */
	private function is_known_provider_host( string $url ): bool {
		// Note for anyone hooking calucon_embed_gate_provider_for_url:
		// resolve_for_asset_host() passes a synthetic "https://host/" to that
		// filter, so it can fire during detection with a URL that never
		// appeared in the markup. Reached only for a URL that already looks
		// like an own-asset path, so it is rare — but it is not the markup's
		// URL, and filter code that inspects $url should not assume it is.
		$host = $this->hosts->host_of( $url );
		if ( null === $host ) {
			return false;
		}
		return null !== $this->providers->resolve_for_asset_host( $host );
	}
}
