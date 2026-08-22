<?php
/**
 * Appearance CSS (PLAN.md §7.1): the settings screen's preset and colour
 * choices as a stylesheet fragment. WordPress-free pure logic — a sanitised
 * appearance array in, a CSS string out — so the emitted rules can be pinned
 * byte-for-byte in the unit suite without booting WordPress.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the inline style attached to the front-end stylesheet.
 */
final class AppearanceCss {

	/**
	 * CSS for the Appearance settings (§7.1): preset + colour overrides of
	 * the §7.3 custom properties. '' when everything is at defaults.
	 *
	 * @param array $appearance Sanitised appearance option subtree.
	 * @return string
	 */
	public static function build( array $appearance ): string {
		// Tolerate pre-0.10 subtrees (missing keys) so the builder stays
		// callable with any sanitised snapshot, old or new.
		$a    = $appearance + array(
			'radius'       => 12,
			'border_width' => '',
			'border_color' => '',
			'shadow'       => '',
			'density'      => '',
		);
		$vars = '';
		foreach ( array(
			'bg'        => '--cg-bg',
			'fg'        => '--cg-fg',
			'accent'    => '--cg-accent',
			'accent_fg' => '--cg-accent-fg',
		) as $option_key => $property ) {
			if ( '' !== $a[ $option_key ] ) {
				$vars .= $property . ':' . $a[ $option_key ] . ';';
			}
		}

		$css = '';
		if ( '' !== $vars ) {
			$css .= '.cg-embed{' . $vars . '}';
		}
		if ( 'minimal' === $a['preset'] ) {
			// Transparent panel on the page's own background; --cg-fg
			// defaults to the theme's contrast preset, so text keeps its
			// ratio against the page.
			$css .= '.cg-embed:not(.cg-embed--active){background:transparent;border:1px solid var(--cg-fg);}';
		} elseif ( 'card' === $a['preset'] ) {
			$css .= '.cg-embed:not(.cg-embed--active){border:1px solid rgba(0,0,0,0.12);border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.18);}';
		}

		// Emitted after the preset rules at equal specificity, so an explicit
		// corner choice beats the card preset's radius. The admin preview
		// (assets/js/admin-appearance.js) mirrors these values inline —
		// change them in both places.
		$radii  = array(
			'square'  => '0',
			'rounded' => '12px',
			'pill'    => '12px',
		);
		$radius = null;
		if ( isset( $radii[ $a['corners'] ] ) ) {
			$radius = $radii[ $a['corners'] ];
		} elseif ( 'custom' === $a['corners'] ) {
			// Sanitised to an int (0–48) in Options; the cast is belt-and-braces.
			$radius = (int) $a['radius'] . 'px';
		}
		if ( null !== $radius ) {
			$css .= '.cg-embed{--cg-radius:' . $radius . ';}.cg-embed:not(.cg-embed--active){border-radius:' . $radius . ';}';
			if ( 'pill' === $a['corners'] ) {
				$css .= '.cg-embed .cg-embed__button{border-radius:999px;}';
			}
		}

		// Border, shadow and spacing follow the same rule as corners: emitted
		// after the preset at equal specificity, so an explicit choice always
		// beats the preset's own border/shadow. An empty value means "let the
		// preset decide" — the pre-0.10 behaviour, byte for byte.
		if ( '' !== (string) $a['border_width'] ) {
			$width = (int) $a['border_width'];
			$color = '' !== $a['border_color'] ? $a['border_color'] : 'var(--cg-fg)';
			$css  .= '.cg-embed:not(.cg-embed--active){border:'
				. ( 0 === $width ? 'none' : $width . 'px solid ' . $color )
				. ';}';
		} elseif ( '' !== $a['border_color'] ) {
			// Colour without a width recolours whatever border the preset
			// draws (minimal, card); with no preset border it does nothing.
			$css .= '.cg-embed:not(.cg-embed--active){border-color:' . $a['border_color'] . ';}';
		}

		$shadows = array(
			'none'   => 'none',
			'soft'   => '0 1px 4px rgba(0,0,0,0.18)',
			'strong' => '0 6px 24px rgba(0,0,0,0.35)',
		);
		if ( isset( $shadows[ $a['shadow'] ] ) ) {
			$css .= '.cg-embed:not(.cg-embed--active){box-shadow:' . $shadows[ $a['shadow'] ] . ';}';
		}

		$gaps = array(
			'compact'  => '0.5rem',
			'spacious' => '1.25rem',
		);
		if ( isset( $gaps[ $a['density'] ] ) ) {
			$css .= '.cg-embed{--cg-gap:' . $gaps[ $a['density'] ] . ';}';
		}

		return $css;
	}
}
