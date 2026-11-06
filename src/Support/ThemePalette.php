<?php
/**
 * The active theme's colour palette (PLAN.md §7.3): the one list behind the
 * settings pickers' swatches, the "Theme colour" selects, and the emitted
 * var(--wp--preset--color--…, #fallback) references.
 *
 * WordPress-aware by necessity (theme.json / editor-color-palette), like
 * Support/CacheFlush.php; everything it returns is hex-only and slug-only so
 * nothing downstream ever interpolates free-form text into CSS.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme palette access.
 */
final class ThemePalette {

	/** Slug grammar — the only thing ever interpolated into a custom-property name. */
	public const SLUG_PATTERN = '/^[a-z0-9-]{1,64}$/';

	/**
	 * Hex grammar for a THEME colour: #rgb, #rgba, #rrggbb or #rrggbbaa.
	 *
	 * Themes do ship translucent presets — WordPress 7.2's default theme,
	 * Ipsum, paints its text with `contrast: #000000bf`. Rejecting those as
	 * "not a colour" left the Appearance preview on a stock 7.2 site without
	 * the theme's text colour: the built-in light-grey fallback on Ipsum's
	 * near-white base, and two false "hard to read" lines on an untouched
	 * site. A theme colour is only ever referenced by its slug and painted
	 * as-is (every browser has read 8-digit hex since 2016), so the alpha
	 * passes straight through. The owner's OWN hex stays opaque — 3 or 6
	 * digits, Options::sanitize() — because the colour picker has no alpha.
	 */
	public const HEX_PATTERN = '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/';

	/**
	 * Palette entries: theme.json colours (the theme's, then the site's
	 * custom ones) on block themes, else the classic editor palette.
	 * Entries without a valid slug AND hex are dropped; duplicates by slug
	 * collapse to the first.
	 *
	 * @return array<int,array{name:string,slug:string,color:string}>
	 */
	public static function entries(): array {
		$raw = array();
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$settings = wp_get_global_settings( array( 'color', 'palette' ) );
			foreach ( array( 'theme', 'custom' ) as $origin ) {
				if ( ! empty( $settings[ $origin ] ) && is_array( $settings[ $origin ] ) ) {
					$raw = array_merge( $raw, $settings[ $origin ] );
				}
			}
		}
		if ( empty( $raw ) && function_exists( 'get_theme_support' ) ) {
			$support = get_theme_support( 'editor-color-palette' );
			if ( is_array( $support ) && isset( $support[0] ) && is_array( $support[0] ) ) {
				$raw = $support[0];
			}
		}

		return self::normalize( $raw );
	}

	/**
	 * The pure half of entries(): raw palette rows (theme.json shape —
	 * slug, color, name) to the validated list. Entries without a valid slug
	 * AND hex are dropped; duplicates by slug collapse to the first; at most
	 * 32 survive. WordPress-free so the grammar is unit-testable.
	 *
	 * @param array<int,mixed> $raw Raw palette rows.
	 * @return array<int,array{name:string,slug:string,color:string}>
	 */
	public static function normalize( array $raw ): array {
		$entries = array();
		$seen    = array();
		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['color'] ) || ! is_string( $entry['color'] ) || empty( $entry['slug'] ) || ! is_string( $entry['slug'] ) ) {
				continue;
			}
			$color = strtolower( trim( $entry['color'] ) );
			$slug  = strtolower( trim( $entry['slug'] ) );
			if ( ! preg_match( self::HEX_PATTERN, $color ) || ! preg_match( self::SLUG_PATTERN, $slug ) || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$entries[]     = array(
				'name'  => isset( $entry['name'] ) && is_string( $entry['name'] ) && '' !== $entry['name'] ? $entry['name'] : $slug,
				'slug'  => $slug,
				'color' => $color,
			);
		}
		return array_slice( $entries, 0, 32 );
	}

	/**
	 * slug => hex, for emitting fallbacks.
	 *
	 * @return array<string,string>
	 */
	public static function map(): array {
		$map = array();
		foreach ( self::entries() as $entry ) {
			$map[ $entry['slug'] ] = $entry['color'];
		}
		return $map;
	}
}
