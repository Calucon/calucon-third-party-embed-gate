<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Providers\Builtin\Descriptors;
use ConsentGate\Support\Options;
use ConsentGate\Tests\Support\PipelineFactory;
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
}
