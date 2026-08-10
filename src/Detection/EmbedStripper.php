<?php
/**
 * Removes third-party embeds instead of gating them — for contexts where a
 * placeholder is nonsense: excerpts and feeds (PLAN.md §3.3, §9.3).
 *
 * WordPress-free by design (PLAN.md §2.2).
 *
 * @package ConsentGate
 */

namespace ConsentGate\Detection;

use ConsentGate\Providers\Registry;

/**
 * Strips foreign iframes, embeds, objects and script tags. §9.3: "strip the
 * embed and emit the fallback link instead" — a feed reader still deserves a
 * route to the content, and before this a YouTube iframe in RSS left
 * nothing at all. The WordPress pair's own fallback blockquote (a plain
 * link) is first-party markup and simply stays.
 */
final class EmbedStripper {

	/** @var HtmlScanner */
	private HtmlScanner $scanner;

	/** @var HostMatcher */
	private HostMatcher $hosts;

	/** @var Registry|null Derives the human fallback URL per provider. */
	private ?Registry $providers;

	/** @var callable Translation function; identity outside WordPress. */
	private $translate;

	public function __construct(
		HtmlScanner $scanner,
		HostMatcher $hosts,
		?Registry $providers = null,
		?callable $translate = null
	) {
		$this->scanner   = $scanner;
		$this->hosts     = $hosts;
		$this->providers = $providers;
		$this->translate = $translate ?? static function ( string $text ): string {
			return $text;
		};
	}

	/**
	 * @param string $html Content HTML.
	 * @return string Content with third-party embeds removed; the fallback
	 *                link (or nothing) in their place.
	 */
	public function strip( string $html ): string {
		if ( false !== stripos( $html, '<iframe' ) ) {
			$html = $this->strip_tag( $html, 'iframe', 'src', true );
		}
		if ( false !== stripos( $html, '<object' ) ) {
			$html = $this->strip_tag( $html, 'object', 'data', true );
		}
		if ( false !== stripos( $html, '<embed' ) ) {
			$html = $this->strip_tag( $html, 'embed', 'src', true );
		}
		if ( false !== stripos( $html, '<script' ) ) {
			// A script SDK's companion element (with its canonical link)
			// stays in the output, so no substitute link is needed — and a
			// bare script URL is not a human destination (§9.5).
			$html = $this->strip_tag( $html, 'script', 'src', false );
		}
		return $html;
	}

	/**
	 * @param string $html          Content.
	 * @param string $tag           Tag name.
	 * @param string $url_attribute Attribute carrying the URL.
	 * @param bool   $emit_fallback Substitute a plain link for the embed.
	 * @return string
	 */
	private function strip_tag( string $html, string $tag, string $url_attribute, bool $emit_fallback ): string {
		foreach ( array_reverse( $this->scanner->find_tags( $html, $tag ) ) as $tag_match ) {
			$attributes = $tag_match['attributes'];
			if ( ! isset( $attributes[ $url_attribute ] ) || ! is_string( $attributes[ $url_attribute ] ) ) {
				continue;
			}
			$src = trim( $attributes[ $url_attribute ] );
			if ( HostMatcher::FOREIGN !== $this->hosts->classify( $src ) ) {
				continue;
			}

			$replacement = $emit_fallback ? $this->fallback_link( $src, $html, $tag_match['start'] ) : '';

			$html = substr( $html, 0, $tag_match['start'] ) . $replacement . substr( $html, $tag_match['end'] );
		}

		return $html;
	}

	/**
	 * A plain link to the content for feed readers. Suppressed when the
	 * WordPress fallback blockquote directly precedes the embed — its link
	 * is already the canonical one, and doubling it is noise.
	 *
	 * @param string $src          Embed URL.
	 * @param string $html         Full fragment.
	 * @param int    $embed_start  Offset of the embed's '<'.
	 * @return string
	 */
	private function fallback_link( string $src, string $html, int $embed_start ): string {
		$before = rtrim( substr( $html, 0, $embed_start ) );
		if ( preg_match( '/<\/blockquote\s*>$/i', $before )
			&& false !== stripos( $before, 'wp-embedded-content' ) ) {
			return '';
		}

		$host = $this->hosts->host_of( $src );
		if ( null === $host ) {
			return '';
		}

		$url   = $src;
		$label = $host;
		if ( null !== $this->providers ) {
			$provider = $this->providers->resolve_for_url( $src, $host );
			if ( is_string( $provider['fallback'] ) && '' !== $provider['fallback'] ) {
				$url = $provider['fallback'];
			}
			if ( is_string( $provider['label'] ) && '' !== $provider['label'] ) {
				$label = $provider['label'];
			}
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		$t = $this->translate;
		/* translators: %s: provider label (usually a host name). */
		$text = sprintf( $t( 'Open on %s' ), $label );

		return '<p><a href="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . '">'
			. htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) . '</a></p>';
	}
}
