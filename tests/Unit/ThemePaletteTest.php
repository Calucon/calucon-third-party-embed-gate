<?php
/**
 * The theme-palette grammar. WordPress 7.2's default theme, Ipsum, ships
 * translucent presets (contrast: #000000bf); a reader that only knew 3- and
 * 6-digit hex dropped them, and the Appearance preview on a stock 7.2 site
 * lost the theme's text colour. These pin what the reader accepts and what
 * it still refuses.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Support\ThemePalette;
use PHPUnit\Framework\TestCase;

final class ThemePaletteTest extends TestCase {

	public function test_translucent_presets_are_kept_with_their_alpha(): void {
		$entries = ThemePalette::normalize(
			array(
				array( 'slug' => 'base', 'color' => '#FCFCFC', 'name' => 'Base' ),
				array( 'slug' => 'contrast', 'color' => '#000000BF', 'name' => 'Contrast' ),
				array( 'slug' => 'accent-5', 'color' => '#0a0a0a08' ),
				array( 'slug' => 'short', 'color' => '#ABCD', 'name' => 'Short' ),
			)
		);

		self::assertSame(
			array(
				array( 'name' => 'Base', 'slug' => 'base', 'color' => '#fcfcfc' ),
				array( 'name' => 'Contrast', 'slug' => 'contrast', 'color' => '#000000bf' ),
				array( 'name' => 'accent-5', 'slug' => 'accent-5', 'color' => '#0a0a0a08' ),
				array( 'name' => 'Short', 'slug' => 'short', 'color' => '#abcd' ),
			),
			$entries,
			'8- and 4-digit hex are theme colours too; lowercased, the alpha kept, the name falling back to the slug'
		);
	}

	public function test_anything_that_is_not_hex_is_still_refused(): void {
		$entries = ThemePalette::normalize(
			array(
				array( 'slug' => 'five', 'color' => '#00000' ),
				array( 'slug' => 'seven', 'color' => '#1234567' ),
				array( 'slug' => 'nine', 'color' => '#123456789' ),
				array( 'slug' => 'letters', 'color' => '#abcdefgh' ),
				array( 'slug' => 'func', 'color' => 'rgb(0, 0, 0)' ),
				array( 'slug' => 'var', 'color' => 'var(--wp--preset--color--base)' ),
				array( 'slug' => 'name', 'color' => 'red' ),
				array( 'slug' => 'Bad Slug', 'color' => '#ffffff' ),
				array( 'slug' => 'nocolor' ),
				'not an array',
				array( 'slug' => 'ok', 'color' => '#ffffff' ),
			)
		);

		self::assertSame( array( array( 'name' => 'ok', 'slug' => 'ok', 'color' => '#ffffff' ) ), $entries );
	}

	public function test_duplicate_slugs_collapse_to_the_first_and_the_list_is_capped(): void {
		$raw = array( array( 'slug' => 'dup', 'color' => '#111111' ), array( 'slug' => 'dup', 'color' => '#222222' ) );
		self::assertSame( array( array( 'name' => 'dup', 'slug' => 'dup', 'color' => '#111111' ) ), ThemePalette::normalize( $raw ) );

		$many = array();
		for ( $i = 0; $i < 40; $i++ ) {
			$many[] = array( 'slug' => 'c' . $i, 'color' => '#000000' );
		}
		self::assertCount( 32, ThemePalette::normalize( $many ) );
	}

	public function test_the_pattern_is_the_one_the_front_end_fallback_trusts(): void {
		// AppearanceCss::color() paints a theme hex verbatim inside var(); it
		// must trust exactly this grammar, and nothing wider.
		foreach ( array( '#abc', '#abcd', '#abcdef', '#abcdef01' ) as $ok ) {
			self::assertMatchesRegularExpression( ThemePalette::HEX_PATTERN, $ok );
		}
		foreach ( array( '#ab', '#abcde', '#abcdef0', '#abcdef012', 'abc', '#ABC' ) as $bad ) {
			self::assertDoesNotMatchRegularExpression( ThemePalette::HEX_PATTERN, $bad, 'the pattern is applied after strtolower(); uppercase is normalised, not matched' );
		}
	}
}
