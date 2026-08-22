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
			'.cg-embed{--cg-bg:#112233;--cg-accent:#abcdef;}',
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
			'.cg-embed{--cg-radius:0;}.cg-embed:not(.cg-embed--active){border-radius:0;}',
			AppearanceCss::build( self::appearance( array( 'corners' => 'square' ) ) )
		);
		// Corner rules are emitted after the preset's so they win at equal
		// specificity — an explicit choice beats the card radius.
		self::assertSame(
			'.cg-embed:not(.cg-embed--active){border:1px solid rgba(0,0,0,0.12);border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.18);}'
			. '.cg-embed{--cg-radius:12px;}.cg-embed:not(.cg-embed--active){border-radius:12px;}'
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
			'.cg-embed{--cg-radius:20px;}.cg-embed:not(.cg-embed--active){border-radius:20px;}',
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
}
