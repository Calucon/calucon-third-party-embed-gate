<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\EmbedStripper;
use CaluconEmbedGate\Detection\HostMatcher;
use CaluconEmbedGate\Detection\HtmlScanner;
use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Providers\Registry;
use PHPUnit\Framework\TestCase;

final class EmbedStripperTest extends TestCase {

	private EmbedStripper $stripper;

	protected function setUp(): void {
		$this->stripper = new EmbedStripper(
			new HtmlScanner(),
			new HostMatcher( array( 'example.test' ) ),
			new Registry( Descriptors::all() )
		);
	}

	public function test_foreign_iframe_is_replaced_with_the_fallback_link(): void {
		// §9.3: strip the embed and emit the fallback link instead — a feed
		// reader still deserves a route to the content. The registry derives
		// the canonical page (watch URL), not the embed endpoint.
		$html = '<p>Intro.</p><iframe src="https://www.youtube.com/embed/y_pjE_p1HwE" title="T"></iframe><p>Outro.</p>';

		self::assertSame(
			'<p>Intro.</p><p><a href="https://www.youtube.com/watch?v=y_pjE_p1HwE">Open on YouTube</a></p><p>Outro.</p>',
			$this->stripper->strip( $html )
		);
	}

	public function test_legacy_object_and_embed_are_stripped_with_a_link(): void {
		$html = '<object data="https://www.youtube.com/v/x" width="560" height="315"></object>';

		self::assertSame(
			'<p><a href="https://www.youtube.com/v/x">Open on www.youtube.com</a></p>',
			$this->stripper->strip( $html )
		);
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
