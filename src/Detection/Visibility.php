<?php
/**
 * Shared invisible-element heuristic for the detection rules.
 *
 * WordPress-free by design (PLAN.md §2.2).
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether an element is invisible to the visitor: zero-sized, hidden, or
 * display:none. Matches the shapes trackers actually use (GTM's noscript
 * pixel is height=0 width=0 style="display:none;visibility:hidden").
 * Deliberately NOT matched: visibility:hidden alone — core's own
 * WordPress-to-WordPress embed iframe ships hidden that way until
 * wp-embed.js reveals it, and it is real content.
 */
final class Visibility {

	/**
	 * @param array $attributes Lowercased, decoded attributes.
	 * @return bool
	 */
	public static function is_invisible( array $attributes ): bool {
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
