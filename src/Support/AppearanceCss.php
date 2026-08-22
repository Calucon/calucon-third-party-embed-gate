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
		$a    = $appearance;
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
		$radii = array(
			'square'  => '0',
			'rounded' => '12px',
			'pill'    => '12px',
		);
		if ( isset( $radii[ $a['corners'] ] ) ) {
			$radius = $radii[ $a['corners'] ];
			$css   .= '.cg-embed{--cg-radius:' . $radius . ';}.cg-embed:not(.cg-embed--active){border-radius:' . $radius . ';}';
			if ( 'pill' === $a['corners'] ) {
				$css .= '.cg-embed .cg-embed__button{border-radius:999px;}';
			}
		}

		return $css;
	}
}
