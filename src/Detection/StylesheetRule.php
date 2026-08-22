<?php
/**
 * Stylesheet companions of a gated provider (PLAN.md §3.5).
 *
 * Some oEmbed outputs (Wolfram Cloud's notebook embedder) paste provider
 * stylesheets into the content next to the script that needs them. A
 * <link rel="stylesheet"> is a request on load, so it must not survive
 * before the click — but it is not an embed either: it becomes a silent
 * companion of the provider's panel and is re-added when that panel is
 * activated. Stylesheets of unknown hosts are left alone (a theme's fonts
 * are the Compatibility screen's business, not a gate's). WordPress-free.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Detection;

use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Rendering\PlaceholderRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces provider stylesheets in content with silent companions.
 */
final class StylesheetRule {

	/** @var HtmlScanner */
	private HtmlScanner $scanner;

	/** @var HostMatcher */
	private HostMatcher $hosts;

	/** @var Registry */
	private Registry $providers;

	/** @var PlaceholderRenderer */
	private PlaceholderRenderer $renderer;

	/**
	 * @param HtmlScanner         $scanner   Tag reader.
	 * @param HostMatcher         $hosts     Own-host classifier.
	 * @param Registry            $providers Provider registry.
	 * @param PlaceholderRenderer $renderer  Placeholder renderer.
	 */
	public function __construct( HtmlScanner $scanner, HostMatcher $hosts, Registry $providers, PlaceholderRenderer $renderer ) {
		$this->scanner   = $scanner;
		$this->hosts     = $hosts;
		$this->providers = $providers;
		$this->renderer  = $renderer;
	}

	/**
	 * @param string $html Content HTML (after the script rule).
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function apply( string $html, array $ctx = array() ): string {
		if ( false === stripos( $html, '<link' ) || false === strpos( $html, 'data-cg-provider="' ) ) {
			return $html;
		}
		foreach ( array_reverse( $this->scanner->find_tags( $html, 'link' ) ) as $match ) {
			$attributes = $match['attributes'];
			$rel        = isset( $attributes['rel'] ) && is_string( $attributes['rel'] ) ? strtolower( $attributes['rel'] ) : '';
			$rels       = preg_split( '/\s+/', trim( $rel ) );
			if ( ! is_array( $rels ) || ! in_array( 'stylesheet', $rels, true ) ) {
				continue;
			}
			if ( ! isset( $attributes['href'] ) || ! is_string( $attributes['href'] ) ) {
				continue;
			}
			$href = trim( $attributes['href'] );
			if ( HostMatcher::FOREIGN !== $this->hosts->classify( $href ) ) {
				continue;
			}
			$host = $this->hosts->host_of( $href );
			if ( null === $host ) {
				continue;
			}
			$provider = $this->providers->resolve_for_asset_host( $host );
			if ( null === $provider || ( false === $provider['enabled'] && empty( $ctx['force_gate'] ) ) ) {
				continue;
			}
			// Only as a companion of a panel already on the page: a lone
			// provider stylesheet is not an embed to offer a button for.
			if ( false === strpos( $html, 'data-cg-provider="' . $provider['id'] . '"' ) ) {
				continue;
			}
			$provider['strategy'] = 'script';
			$placeholder          = $this->renderer->render( $provider, $href, array(), $ctx + array( 'silent' => true ), array( 'tag' => 'link' ) );
			$html                 = substr( $html, 0, $match['start'] ) . $placeholder . substr( $html, $match['end'] );
		}
		return $html;
	}
}
