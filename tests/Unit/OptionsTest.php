<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Support\Options;
use CaluconEmbedGate\Tests\Support\PipelineFactory;
use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase {

	public function test_garbage_input_yields_defaults(): void {
		self::assertSame( Options::defaults(), Options::sanitize( 'nonsense' ) );
		self::assertSame( Options::defaults(), Options::sanitize( null ) );
		self::assertSame( Options::defaults(), Options::sanitize( 42 ) );
	}

	public function test_checkbox_values_become_booleans(): void {
		$clean = Options::sanitize(
			array(
				'providers' => array(
					'youtube' => array( 'enabled' => '0', 'privacy_variant' => '1' ),
				),
				'detection' => array( 'scripts' => '0' ),
			)
		);

		self::assertFalse( $clean['providers']['youtube']['enabled'] );
		self::assertTrue( $clean['providers']['youtube']['privacy_variant'] );
		self::assertFalse( $clean['detection']['scripts'] );
		self::assertTrue( $clean['detection']['iframes'] );
	}

	public function test_note_and_action_are_stripped_of_markup(): void {
		$clean = Options::sanitize(
			array(
				'providers' => array(
					'youtube' => array( 'note' => '  <script>x</script>Custom note ' ),
				),
			)
		);

		self::assertSame( 'xCustom note', $clean['providers']['youtube']['note'] );
	}

	public function test_note_and_action_are_length_capped(): void {
		$clean = Options::sanitize(
			array(
				'providers' => array(
					'youtube' => array(
						'note'   => str_repeat( 'a', 600 ),
						'action' => str_repeat( 'b', 600 ),
					),
				),
			)
		);

		self::assertSame( 500, strlen( $clean['providers']['youtube']['note'] ) );
		self::assertSame( 500, strlen( $clean['providers']['youtube']['action'] ) );
	}

	public function test_host_lists_accept_newline_strings_and_pasted_urls(): void {
		$clean = Options::sanitize(
			array(
				'detection' => array(
					'own_hosts'  => "cdn.example.com\nhttps://media.example.com/path\n*.static.example.com\n\ninvalid host!",
					'never_gate' => array( 'Maps.Example.ORG.' ),
				),
			)
		);

		self::assertSame(
			array( 'cdn.example.com', 'media.example.com', '*.static.example.com' ),
			$clean['detection']['own_hosts']
		);
		self::assertSame( array( 'maps.example.org' ), $clean['detection']['never_gate'] );
	}

	public function test_unknown_provider_ids_are_dropped(): void {
		$clean = Options::sanitize(
			array( 'providers' => array( 'evil provider!' => array( 'enabled' => '0' ) ) )
		);

		self::assertSame( array(), $clean['providers'] );
	}

	public function test_disabled_provider_passes_through(): void {
		$overridden = Options::apply_provider_overrides(
			Descriptors::all(),
			array( 'providers' => array( 'youtube' => array( 'enabled' => false ) ) )
		);

		$input = '<iframe src="https://www.youtube.com/embed/y_pjE_p1HwE" title="T"></iframe>';
		$html  = PipelineFactory::gate( $input, array( 'example.test' ), array(), $overridden );

		self::assertSame( $input, $html );
	}

	public function test_privacy_variant_off_keeps_the_original_host(): void {
		$overridden = Options::apply_provider_overrides(
			Descriptors::all(),
			array( 'providers' => array( 'youtube' => array( 'privacy_variant' => false ) ) )
		);

		$html = PipelineFactory::gate(
			'<iframe src="https://www.youtube.com/embed/y_pjE_p1HwE" title="T"></iframe>',
			array( 'example.test' ),
			array(),
			$overridden
		);

		self::assertStringContainsString( 'www.youtube.com/embed/y_pjE_p1HwE', $html );
		self::assertStringNotContainsString( 'youtube-nocookie.com', $html );
		self::assertStringContainsString( 'data-cg-provider="youtube"', $html );
	}

	public function test_note_override_reaches_the_panel(): void {
		$overridden = Options::apply_provider_overrides(
			Descriptors::all(),
			array( 'providers' => array( 'youtube' => array( 'note' => 'House rules apply.' ) ) )
		);

		$html = PipelineFactory::gate(
			'<iframe src="https://www.youtube.com/embed/y_pjE_p1HwE" title="T"></iframe>',
			array( 'example.test' ),
			array(),
			$overridden
		);

		self::assertStringContainsString( '<p class="cg-embed__note">House rules apply.</p>', $html );
	}

	public function test_appearance_accepts_hex_colours_and_known_presets_only(): void {
		$clean = Options::sanitize(
			array(
				'appearance' => array(
					'preset'    => 'card',
					'bg'        => '#FFFFFF',
					'fg'        => 'red',
					'accent'    => '#12ab34',
					'accent_fg' => 'url(javascript:x)}body{',
				),
			)
		);

		self::assertSame( 'card', $clean['appearance']['preset'] );
		self::assertSame( '#ffffff', $clean['appearance']['bg'] );
		// Non-hex values could smuggle CSS out of the custom property.
		self::assertSame( '', $clean['appearance']['fg'] );
		self::assertSame( '#12ab34', $clean['appearance']['accent'] );
		self::assertSame( '', $clean['appearance']['accent_fg'] );

		$bad = Options::sanitize( array( 'appearance' => array( 'preset' => 'neon' ) ) );
		self::assertSame( 'default', $bad['appearance']['preset'] );
	}

	public function test_appearance_corners_accepts_known_values_only(): void {
		self::assertSame( '', Options::defaults()['appearance']['corners'] );

		$clean = Options::sanitize( array( 'appearance' => array( 'corners' => 'pill' ) ) );
		self::assertSame( 'pill', $clean['appearance']['corners'] );

		// Unknown values fall back to the default — never into emitted CSS.
		$bad = Options::sanitize( array( 'appearance' => array( 'corners' => '12px;}body{' ) ) );
		self::assertSame( '', $bad['appearance']['corners'] );
	}

	public function test_appearance_new_knobs_are_bounded_and_enum_checked(): void {
		$clean = Options::sanitize(
			array(
				'appearance' => array(
					'corners'      => 'custom',
					'radius'       => '999',
					'border_width' => '99',
					'border_color' => '#ABCDEF',
					'shadow'       => 'soft',
					'density'      => 'spacious',
				),
			)
		);

		self::assertSame( 'custom', $clean['appearance']['corners'] );
		self::assertSame( 48, $clean['appearance']['radius'], 'radius clamps to 48' );
		self::assertSame( '10', $clean['appearance']['border_width'], 'border width clamps to 10' );
		self::assertSame( '#abcdef', $clean['appearance']['border_color'], 'hex lowercased like the other colours' );
		self::assertSame( 'soft', $clean['appearance']['shadow'] );
		self::assertSame( 'spacious', $clean['appearance']['density'] );

		$bad = Options::sanitize(
			array(
				'appearance' => array(
					'radius'       => 'huge',
					'border_width' => 'expression(alert(1))',
					'border_color' => 'red',
					'shadow'       => 'dramatic',
					'density'      => 'cosy',
				),
			)
		);

		self::assertSame( 12, $bad['appearance']['radius'] );
		self::assertSame( '', $bad['appearance']['border_width'] );
		self::assertSame( '', $bad['appearance']['border_color'] );
		self::assertSame( '', $bad['appearance']['shadow'] );
		self::assertSame( '', $bad['appearance']['density'] );
	}

	public function test_appearance_round_two_knobs_sanitise(): void {
		$clean = Options::sanitize(
			array(
				'appearance' => array(
					'withdraw_style' => 'link',
					'button_size'    => 'large',
					'play_icon'      => '1',
					'note_size'      => 'small',
					'align'          => 'center',
					'dark'           => '1',
					'dark_bg'        => '#101418',
					'dark_accent_fg' => 'ARGB(1,2,3)',
				),
			)
		);

		self::assertSame( 'link', $clean['appearance']['withdraw_style'] );
		self::assertSame( 'large', $clean['appearance']['button_size'] );
		self::assertTrue( $clean['appearance']['play_icon'] );
		self::assertSame( 'small', $clean['appearance']['note_size'] );
		self::assertSame( 'center', $clean['appearance']['align'] );
		self::assertTrue( $clean['appearance']['dark'] );
		self::assertSame( '#101418', $clean['appearance']['dark_bg'] );
		self::assertSame( '', $clean['appearance']['dark_accent_fg'], 'non-hex dark colour rejected' );

		$bad = Options::sanitize(
			array(
				'appearance' => array(
					'withdraw_style' => 'blinking',
					'button_size'    => 'giant',
					'note_size'      => 'huge',
					'align'          => 'justify',
				),
			)
		);
		self::assertSame( '', $bad['appearance']['withdraw_style'] );
		self::assertSame( '', $bad['appearance']['button_size'] );
		self::assertSame( '', $bad['appearance']['note_size'] );
		self::assertSame( '', $bad['appearance']['align'] );
		self::assertFalse( $bad['appearance']['play_icon'] );
		self::assertFalse( $bad['appearance']['dark'] );
	}

	public function test_privacy_link_toggle_defaults_on_and_becomes_boolean(): void {
		self::assertTrue( Options::sanitize( array() )['display']['privacy_link'] );
		self::assertFalse( Options::sanitize( array( 'display' => array( 'privacy_link' => '0' ) ) )['display']['privacy_link'] );
		self::assertTrue( Options::sanitize( array( 'display' => array( 'privacy_link' => '1' ) ) )['display']['privacy_link'] );
	}

	public function test_always_gate_list_is_sanitised_like_the_other_host_lists(): void {
		$clean = Options::sanitize(
			array(
				'detection' => array(
					'always_gate' => "widgets.example.com\nhttps://Tracking.Example.org/path",
				),
			)
		);

		self::assertSame( array( 'widgets.example.com', 'tracking.example.org' ), $clean['detection']['always_gate'] );
	}

	public function test_cmp_bridge_is_off_by_default(): void {
		$defaults = Options::defaults();

		self::assertFalse( $defaults['cmp']['bridge'] );
		self::assertFalse( $defaults['cmp']['tcf'] );
		self::assertSame( 'external-media', $defaults['cmp']['borlabs_group'] );
	}

	public function test_cmp_flags_become_booleans(): void {
		$clean = Options::sanitize(
			array(
				'cmp' => array(
					'bridge' => '1',
					'tcf'    => '0',
				),
			)
		);

		self::assertTrue( $clean['cmp']['bridge'] );
		self::assertFalse( $clean['cmp']['tcf'] );
	}

	public function test_cmp_borlabs_group_accepts_slugs_only(): void {
		$clean = Options::sanitize( array( 'cmp' => array( 'borlabs_group' => 'Marketing-Group_2' ) ) );
		self::assertSame( 'marketing-group_2', $clean['cmp']['borlabs_group'] );

		// Anything that could break out of the inline config JSON falls
		// back to the default rather than travelling to the page.
		$bad = Options::sanitize( array( 'cmp' => array( 'borlabs_group' => 'x"};alert(1);//' ) ) );
		self::assertSame( 'external-media', $bad['cmp']['borlabs_group'] );
	}
}
