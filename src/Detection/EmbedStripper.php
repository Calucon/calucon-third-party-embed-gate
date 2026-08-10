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

/**
 * Strips foreign iframes (with their WordPress fallback blockquote, whose
 * link is kept — a feed reader still deserves a way to the content) and
 * foreign script tags.
 */
final class EmbedStripper {

	/** @var HtmlScanner */
	private HtmlScanner $scanner;

	/** @var HostMatcher */
	private HostMatcher $hosts;

	public function __construct( HtmlScanner $scanner, HostMatcher $hosts ) {
		$this->scanner = $scanner;
		$this->hosts   = $hosts;
	}

	/**
	 * @param string $html Content HTML.
	 * @return string Content with third-party embeds removed; the fallback
	 *                link (or nothing) in their place.
	 */
	public function strip( string $html ): string {
		if ( false !== stripos( $html, '<iframe' ) ) {
			$html = $this->strip_tag( $html, 'iframe' );
		}
		if ( false !== stripos( $html, '<script' ) ) {
			$html = $this->strip_tag( $html, 'script' );
		}
		return $html;
	}

	/**
	 * @param string $html Content.
	 * @param string $tag  'iframe' or 'script'.
	 * @return string
	 */
	private function strip_tag( string $html, string $tag ): string {
		foreach ( array_reverse( $this->scanner->find_tags( $html, $tag ) ) as $match ) {
			$attributes = $match['attributes'];
			if ( ! isset( $attributes['src'] ) || ! is_string( $attributes['src'] ) ) {
				continue;
			}
			if ( HostMatcher::FOREIGN !== $this->hosts->classify( trim( $attributes['src'] ) ) ) {
				continue;
			}

			$html = substr( $html, 0, $match['start'] ) . substr( $html, $match['end'] );
		}

		return $html;
	}
}
