<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use ConsentGate\Detection\ImageRule;
use ConsentGate\Providers\Builtin\Descriptors;
use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;
use PHPUnit\Framework\TestCase;

final class ImageRuleTest extends TestCase {

	private ImageRule $rule;

	protected function setUp(): void {
		$scanner    = new HtmlScanner();
		$hosts      = new HostMatcher( array( 'example.test' ) );
		$this->rule = new ImageRule( $scanner, $hosts, new Registry( Descriptors::all() ), new PlaceholderRenderer() );
	}

	public function test_hotlinked_third_party_image_is_gated_with_alt_preserved(): void {
		$html = '<img src="https://i.ytimg.com/vi/x/hqdefault.jpg" alt="Video poster" width="480" height="360">';
		$out  = $this->rule->apply( $html );

		self::assertStringContainsString( 'cg-embed', $out );
		self::assertStringContainsString( 'data-cg-host="i.ytimg.com"', $out );
		// alt survives into the payload — dropping it on rebuild would be an
		// accessibility regression.
		self::assertStringContainsString( 'Video poster', $out );
		self::assertStringContainsString( '&quot;tag&quot;:&quot;img&quot;', $out );
		self::assertStringNotContainsString( '<img', $out );
	}

	public function test_own_and_relative_images_pass_through_byte_identical(): void {
		$html = '<img src="https://example.test/a.jpg" alt=""><img src="/wp-content/uploads/b.png" alt="">';

		self::assertSame( $html, $this->rule->apply( $html ) );
	}

	public function test_tracking_pixel_is_removed_outright(): void {
		$html = '<p>Text.</p><img src="https://tracker.example.org/p.gif" width="1" height="1" alt="">';

		self::assertSame( '<p>Text.</p>', $this->rule->apply( $html ) );
	}

	public function test_lazy_data_src_image_is_gated(): void {
		$html = '<img data-src="https://cdn.thirdparty.example/photo.jpg" src="data:image/gif;base64,R0lGOD" alt="Photo">';
		$out  = $this->rule->apply( $html );

		self::assertStringContainsString( 'cg-embed', $out );
		self::assertStringContainsString( 'cdn.thirdparty.example', $out );
	}
}
