<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Detection\EmbedStripper;
use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use PHPUnit\Framework\TestCase;

final class EmbedStripperTest extends TestCase {

	private EmbedStripper $stripper;

	protected function setUp(): void {
		$this->stripper = new EmbedStripper( new HtmlScanner(), new HostMatcher( array( 'example.test' ) ) );
	}

	public function test_foreign_iframe_is_removed_entirely(): void {
		$html = '<p>Intro.</p><iframe src="https://www.youtube.com/embed/x" title="T"></iframe><p>Outro.</p>';

		self::assertSame( '<p>Intro.</p><p>Outro.</p>', $this->stripper->strip( $html ) );
	}

	public function test_wp_embed_pair_keeps_the_fallback_blockquote(): void {
		// The blockquote is a plain link — exactly what a feed reader needs.
		$html = '<blockquote class="wp-embedded-content"><a href="https://other.example/post/">Post</a></blockquote><iframe class="wp-embedded-content" src="https://other.example/post/embed/" title="T"></iframe>';

		self::assertSame(
			'<blockquote class="wp-embedded-content"><a href="https://other.example/post/">Post</a></blockquote>',
			$this->stripper->strip( $html )
		);
	}

	public function test_foreign_script_is_removed_but_companion_stays(): void {
		$html = '<blockquote class="twitter-tweet"><a href="https://twitter.com/a/status/1">x</a></blockquote><script async src="https://platform.twitter.com/widgets.js"></script>';

		self::assertSame(
			'<blockquote class="twitter-tweet"><a href="https://twitter.com/a/status/1">x</a></blockquote>',
			$this->stripper->strip( $html )
		);
	}

	public function test_same_origin_content_is_untouched(): void {
		$html = '<iframe src="https://example.test/player" title="Own"></iframe><script src="/js/app.js"></script><script>var x = 1;</script>';

		self::assertSame( $html, $this->stripper->strip( $html ) );
	}
}
