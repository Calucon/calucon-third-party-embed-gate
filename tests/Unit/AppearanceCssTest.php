<?php
/**
 * The Appearance CSS is emitted inline on every front-end page that gates an
 * embed, so its exact bytes are part of the rendered output — pin them, the
 * same way the fixtures pin the placeholder markup.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Support\AppearanceCss;
use PHPUnit\Framework\TestCase;

final class AppearanceCssTest extends TestCase {

	/**
	 * The sanitised appearance subtree with overrides, as build() receives it.
	 *
	 * @param array $overrides Keys to change from the shipped defaults.
	 * @return array
	 */
	private static function appearance( array $overrides = array() ): array {
		return array_merge(
			array(
				'preset'    => 'default',
				'bg'        => '',
				'fg'        => '',
				'accent'    => '',
				'accent_fg' => '',
				'corners'      => '',
				'radius'       => 12,
				'border_width' => '',
				'border_color' => '',
				'shadow'       => '',
				'density'      => '',
				'withdraw_style' => '',
				'button_size'    => '',
				'play_icon'      => false,
				'note_size'      => '',
				'align'          => '',
				'dark'           => false,
				'dark_bg'        => '',
				'dark_fg'        => '',
				'dark_accent'    => '',
				'dark_accent_fg' => '',
				'button_style'   => '',
				'button_width'   => '',
				'hover'          => '',
				'poster_panel'   => '',
				'poster_dim'     => '',
				'link'           => '',
			),
			$overrides
		);
	}

	public function test_defaults_emit_nothing(): void {
		// '' means wp_add_inline_style() is skipped entirely: at defaults the
		// theme's palette rules, and the page carries no extra style bytes.
		self::assertSame( '', AppearanceCss::build( self::appearance() ) );
	}

	public function test_hex_colors_become_custom_property_overrides(): void {
		self::assertSame(
			'.cg-embed,.cg-withdraw{--cg-bg:#112233;--cg-accent:#abcdef;}',
			AppearanceCss::build(
				self::appearance(
					array(
						'bg'     => '#112233',
						'accent' => '#abcdef',
					)
				)
			)
		);
	}

	public function test_minimal_preset_is_transparent_with_border(): void {
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){background:transparent;border:1px solid var(--cg-fg);}',
			AppearanceCss::build( self::appearance( array( 'preset' => 'minimal' ) ) )
		);
	}

	public function test_card_preset_adds_border_radius_and_shadow(): void {
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){border:1px solid rgba(0,0,0,0.12);border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.18);}',
			AppearanceCss::build( self::appearance( array( 'preset' => 'card' ) ) )
		);
	}

	public function test_corners_override_panel_radius_and_pill_rounds_the_button(): void {
		self::assertSame(
			'.cg-embed,.cg-withdraw{--cg-radius:0;}.cg-embed:not(.cg-embed--active){border-radius:0;}',
			AppearanceCss::build( self::appearance( array( 'corners' => 'square' ) ) )
		);
		// Corner rules are emitted after the preset's so they win at equal
		// specificity — an explicit choice beats the card radius.
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){border:1px solid rgba(0,0,0,0.12);border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.18);}'
			. '.cg-embed,.cg-withdraw{--cg-radius:12px;}.cg-embed:not(.cg-embed--active){border-radius:12px;}'
			. '.cg-embed .cg-embed__button{border-radius:999px;}',
			AppearanceCss::build(
				self::appearance(
					array(
						'preset'  => 'card',
						'corners' => 'pill',
					)
				)
			)
		);
	}

	public function test_custom_corner_radius_emits_the_px_value(): void {
		self::assertSame(
			'.cg-embed,.cg-withdraw{--cg-radius:20px;}.cg-embed:not(.cg-embed--active){border-radius:20px;}',
			AppearanceCss::build( self::appearance( array( 'corners' => 'custom', 'radius' => 20 ) ) )
		);
	}

	public function test_border_width_uses_fg_when_no_color_chosen(): void {
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){border:3px solid var(--cg-fg);}',
			AppearanceCss::build( self::appearance( array( 'border_width' => '3' ) ) )
		);
	}

	public function test_border_width_with_color(): void {
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){border:2px solid #ff8800;}',
			AppearanceCss::build( self::appearance( array( 'border_width' => '2', 'border_color' => '#ff8800' ) ) )
		);
	}

	public function test_border_width_zero_removes_even_the_preset_border(): void {
		// Emitted AFTER the preset at equal specificity, so it wins.
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){background:transparent;border:1px solid var(--cg-fg);}'
			. '.cg-embed:not(.cg-embed--active){border:none;}',
			AppearanceCss::build( self::appearance( array( 'preset' => 'minimal', 'border_width' => '0' ) ) )
		);
	}

	public function test_border_color_alone_recolors_the_preset_border(): void {
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){border-color:#00ff00;}',
			AppearanceCss::build( self::appearance( array( 'border_color' => '#00ff00' ) ) )
		);
	}

	public function test_shadow_and_density_choices(): void {
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){box-shadow:none;}',
			AppearanceCss::build( self::appearance( array( 'shadow' => 'none' ) ) )
		);
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){box-shadow:0 6px 24px rgba(0,0,0,0.35);}',
			AppearanceCss::build( self::appearance( array( 'shadow' => 'strong' ) ) )
		);
		self::assertSame(
			'.cg-embed{--cg-gap:0.5rem;}',
			AppearanceCss::build( self::appearance( array( 'density' => 'compact' ) ) )
		);
	}

	public function test_pre_010_subtree_without_new_keys_still_builds(): void {
		// Snapshots sanitised before the 0.10 keys existed must not notice.
		self::assertSame(
			'',
			AppearanceCss::build(
				array(
					'preset'    => 'default',
					'bg'        => '',
					'fg'        => '',
					'accent'    => '',
					'accent_fg' => '',
					'corners'   => '',
				)
			)
		);
	}

	public function test_button_size_reaches_load_and_withdraw_buttons(): void {
		self::assertSame(
			'.cg-embed .cg-embed__button,.cg-withdraw{font-size:0.875em;padding:0.375em 0.75em;}',
			AppearanceCss::build( self::appearance( array( 'button_size' => 'small' ) ) )
		);
		self::assertSame(
			'.cg-embed .cg-embed__button,.cg-withdraw{font-size:1.125em;padding:0.625em 1.25em;}',
			AppearanceCss::build( self::appearance( array( 'button_size' => 'large' ) ) )
		);
	}

	public function test_play_icon_is_a_masked_inline_svg_never_a_fetch(): void {
		$css = AppearanceCss::build( self::appearance( array( 'play_icon' => true ) ) );

		self::assertStringContainsString( '.cg-embed .cg-embed__button::before', $css );
		self::assertStringContainsString( 'data:image/svg+xml', $css );
		self::assertStringContainsString( 'background:currentColor', $css );
		self::assertStringContainsString( 'margin-inline-end:0.5em', $css, 'logical margin — RTL sites' );
		// Invariant 9: the only url() in the emission is the data: one.
		self::assertSame( substr_count( $css, 'url(' ), substr_count( $css, 'url("data:' ) );
	}

	public function test_icon_is_kind_aware_per_provider_and_generic_otherwise(): void {
		$css = AppearanceCss::build(
			self::appearance( array( 'play_icon' => true ) ),
			array(
				'youtube'     => 'video',
				'vimeo'       => 'video',
				'google-maps' => 'map',
				'spotify'     => 'audio',
				'typeform'    => '',
				'evil"]'      => 'video', // not a slug: never interpolated into a selector
			)
		);

		self::assertStringContainsString( '.cg-embed[data-cg-provider="youtube"] .cg-embed__button::before,.cg-embed[data-cg-provider="vimeo"] .cg-embed__button::before{', $css );
		self::assertStringContainsString( '.cg-embed[data-cg-provider="google-maps"] .cg-embed__button::before{', $css );
		self::assertStringContainsString( '.cg-embed[data-cg-provider="spotify"] .cg-embed__button::before{', $css );
		self::assertStringNotContainsString( 'typeform', $css, 'generic providers use the base glyph' );
		self::assertStringNotContainsString( 'evil', $css );
		// Four glyph rules (base + video + map + audio), each emitted as
		// -webkit-mask AND mask → eight inline SVGs.
		self::assertSame( 8, substr_count( $css, "viewBox='0 0 16 16'" ) );
	}

	public function test_poster_dim_and_link_colour(): void {
		self::assertSame(
			'.cg-embed--poster:not(.cg-embed--active) .cg-embed__poster{filter:brightness(0.5) blur(2px);}',
			AppearanceCss::build( self::appearance( array( 'poster_dim' => 'strong' ) ) )
		);
		self::assertSame(
			'.cg-embed .cg-embed__fallback a,.cg-embed .cg-embed__privacy a{color:#0a5bd3;}',
			AppearanceCss::build( self::appearance( array( 'link' => '#0a5bd3' ) ) )
		);
	}

	public function test_note_size_and_alignment(): void {
		self::assertSame(
			'.cg-embed .cg-embed__note{font-size:0.875em;}',
			AppearanceCss::build( self::appearance( array( 'note_size' => 'small' ) ) )
		);
		self::assertSame(
			'.cg-embed .cg-embed__panel{align-items:center;text-align:center;}',
			AppearanceCss::build( self::appearance( array( 'align' => 'center' ) ) )
		);
	}

	public function test_dark_palette_emits_only_inside_the_media_query_and_only_when_enabled(): void {
		self::assertSame(
			'@media (prefers-color-scheme:dark){.cg-embed,.cg-withdraw{--cg-bg:#101418;--cg-accent:#7ab648;}}',
			AppearanceCss::build( self::appearance( array( 'dark' => true, 'dark_bg' => '#101418', 'dark_accent' => '#7ab648' ) ) )
		);
		// Colours set but the toggle off: nothing leaks.
		self::assertSame(
			'',
			AppearanceCss::build( self::appearance( array( 'dark' => false, 'dark_bg' => '#101418' ) ) )
		);
		// Toggle on but no colours chosen: no empty media block.
		self::assertSame(
			'',
			AppearanceCss::build( self::appearance( array( 'dark' => true ) ) )
		);
	}

	public function test_withdraw_style_emits_no_css(): void {
		// The variant is a class on the shortcode markup; the stylesheet
		// rules are static in gate.css.
		self::assertSame( '', AppearanceCss::build( self::appearance( array( 'withdraw_style' => 'outline' ) ) ) );
	}

	public function test_button_style_width_and_hover(): void {
		self::assertSame(
			'.cg-embed .cg-embed__button{background:transparent;color:var(--cg-fg);border-color:var(--cg-accent);}',
			AppearanceCss::build( self::appearance( array( 'button_style' => 'outline' ) ) )
		);
		self::assertSame(
			'.cg-embed .cg-embed__button{width:100%;}',
			AppearanceCss::build( self::appearance( array( 'button_width' => 'full' ) ) )
		);
		self::assertSame(
			'.cg-embed .cg-embed__button:hover{filter:none;}',
			AppearanceCss::build( self::appearance( array( 'hover' => 'none' ) ) )
		);
		self::assertSame(
			'.cg-embed .cg-embed__button:hover{filter:brightness(1.25);}',
			AppearanceCss::build( self::appearance( array( 'hover' => 'strong' ) ) )
		);
	}

	public function test_poster_panel_placement(): void {
		self::assertSame(
			'.cg-embed--poster:not(.cg-embed--active) .cg-embed__panel{align-self:center;justify-self:center;}',
			AppearanceCss::build( self::appearance( array( 'poster_panel' => 'center' ) ) )
		);
		self::assertSame(
			'.cg-embed--poster:not(.cg-embed--active) .cg-embed__panel{align-self:end;justify-self:stretch;margin:0;max-width:none;border-radius:0 0 var(--cg-radius) var(--cg-radius);}',
			AppearanceCss::build( self::appearance( array( 'poster_panel' => 'bar' ) ) )
		);
	}
}
