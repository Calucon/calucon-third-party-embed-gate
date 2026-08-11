<?php
/**
 * The third-party <img> gating rule (PLAN.md §3.5).
 *
 * Opt-in (detection.images, default off): replacing remote images with
 * panels can break layouts, and images are content more often than embeds
 * are. When enabled: a hotlinked image is a request to a third party with
 * the visitor's IP attached, exactly like an iframe — and a zero-sized one
 * is a tracking pixel and is removed outright.
 *
 * WordPress-free by design (PLAN.md §2.2).
 *
 * @package ConsentGate
 */

namespace ConsentGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;

/**
 * Replaces cross-origin <img> elements with the placeholder. The rebuilt
 * element is an <img> again — src only, no srcset, never wider capability.
 */
final class ImageRule {

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
	 * Gate every cross-origin <img> in a fragment.
	 *
	 * @param string $html Content HTML.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function apply( string $html, array $ctx = array() ): string {
		if ( false === stripos( $html, '<img' ) ) {
			return $html;
		}

		foreach ( array_reverse( $this->scanner->find_tags( $html, 'img' ) ) as $tag_match ) {
			$attributes = $tag_match['attributes'];

			$src = $this->foreign_src( $attributes );
			if ( null === $src ) {
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

			$provider = $this->providers->resolve_for_url( $src, $host );
			if ( empty( $ctx['force_gate'] ) && false === $provider['enabled'] ) {
				continue;
			}

			// A zero-sized or hidden foreign image is a tracking pixel, not
			// content: nothing to offer a panel for, nothing to link to.
			if ( $this->is_invisible( $attributes ) ) {
				$html = substr( $html, 0, $tag_match['start'] ) . substr( $html, $tag_match['end'] );
				continue;
			}

			$provider['strategy'] = 'iframe';
			if ( '' === $provider['fallback'] ) {
				$provider['fallback'] = $src;
			}

			$placeholder = $this->renderer->render(
				$provider,
				$src,
				$attributes,
				$ctx,
				array( 'tag' => 'img' )
			);

			$html = substr( $html, 0, $tag_match['start'] ) . $placeholder . substr( $html, $tag_match['end'] );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}

		return $html;
	}

	/**
	 * The image's foreign URL: src, or a lazy-load data attribute shim.
	 * A foreign srcset with an own-host src is NOT gated — rebuilding src
	 * only would be the same picture, so the own copy simply wins.
	 *
	 * @param array $attributes Lowercased, decoded attributes.
	 * @return string|null
	 */
	private function foreign_src( array $attributes ) {
		foreach ( array( 'src', 'data-src', 'data-lazy-src' ) as $name ) {
			if ( isset( $attributes[ $name ] ) && is_string( $attributes[ $name ] ) ) {
				$candidate = trim( $attributes[ $name ] );
				if ( HostMatcher::FOREIGN === $this->hosts->classify( $candidate ) ) {
					return $candidate;
				}
			}
		}
		return null;
	}

	/**
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
}
