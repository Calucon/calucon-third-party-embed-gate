<?php
/**
 * ElementorVideoRule: the edges the fixtures do not reach.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\ElementorVideoRule;
use CaluconEmbedGate\Detection\HostMatcher;
use CaluconEmbedGate\Detection\HtmlScanner;
use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Rendering\PlaceholderRenderer;
use PHPUnit\Framework\TestCase;

final class ElementorVideoRuleTest extends TestCase {

	private const WIDGET = '<div class="elementor-element elementor-widget elementor-widget-video" data-id="cg1" data-settings="%s" data-widget_type="video.default"><div class="elementor-widget-container"><div class="elementor-wrapper elementor-open-inline">%s</div></div></div>';

	private static function widget( array $settings, string $inner = '<div class="elementor-video"></div>' ): string {
		return sprintf( self::WIDGET, htmlspecialchars( (string) json_encode( $settings ), ENT_QUOTES ), $inner );
	}

	private static function rule( ?array $providers = null, ?callable $should_gate = null ): ElementorVideoRule {
		return new ElementorVideoRule(
			new HtmlScanner(),
			new HostMatcher( array( 'example.test' ) ),
			new Registry( null === $providers ? Descriptors::all() : $providers ),
			new PlaceholderRenderer(),
			$should_gate
		);
	}

	/** @dataProvider urls */
	public function test_youtube_id_from_every_url_shape_an_owner_pastes( string $url, string $expected ): void {
		self::assertSame( $expected, ElementorVideoRule::youtube_id( $url ) );
	}

	public static function urls(): array {
		return array(
			'watch'           => array( 'https://www.youtube.com/watch?v=y_pjE_p1HwE', 'y_pjE_p1HwE' ),
			'watch with more' => array( 'https://www.youtube.com/watch?t=42&v=y_pjE_p1HwE&list=PL1', 'y_pjE_p1HwE' ),
			'short link'      => array( 'https://youtu.be/y_pjE_p1HwE?si=abc', 'y_pjE_p1HwE' ),
			'embed'           => array( 'https://www.youtube-nocookie.com/embed/y_pjE_p1HwE', 'y_pjE_p1HwE' ),
			'shorts'          => array( 'https://www.youtube.com/shorts/y_pjE_p1HwE', 'y_pjE_p1HwE' ),
			'live'            => array( 'https://www.youtube.com/live/y_pjE_p1HwE', 'y_pjE_p1HwE' ),
			'not a video'     => array( 'https://www.youtube.com/@calucon', '' ),
			'empty'           => array( '', '' ),
			'hostile id'      => array( 'https://www.youtube.com/watch?v=<script>', '' ),
		);
	}

	public function test_a_gated_widget_is_rewritten_so_elementor_stands_down_and_a_second_pass_leaves_it(): void {
		$html = self::widget( array( 'video_type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=y_pjE_p1HwE', 'controls' => 'yes' ) );
		$once = self::rule()->apply( $html );

		self::assertStringContainsString( 'class="cg-embed"', $once );
		self::assertStringContainsString( 'data-cg-provider="youtube"', $once );
		self::assertStringContainsString( 'www.youtube-nocookie.com/embed/y_pjE_p1HwE', $once );
		// The honest no-JS link is the watch page the owner pasted.
		self::assertStringContainsString( 'href="https://www.youtube.com/watch?v=y_pjE_p1HwE"', $once );
		// Elementor's handler reads video_type first and bails unless it is
		// 'youtube'; the URL it would have used is gone too.
		self::assertStringContainsString( '&quot;video_type&quot;:&quot;' . ElementorVideoRule::GATED_TYPE . '&quot;', $once );
		self::assertStringNotContainsString( 'youtube_url', $once );
		self::assertStringContainsString( '&quot;controls&quot;:&quot;yes&quot;', $once, 'unrelated settings survive' );
		self::assertStringNotContainsString( '<div class="elementor-video"></div>', $once, 'the JS-built player container is gone' );
		self::assertStringNotContainsString( '<iframe', $once );

		self::assertSame( $once, self::rule()->apply( $once ), 'idempotent' );
	}

	public function test_non_youtube_types_and_lightbox_mode_are_left_alone(): void {
		$vimeo = self::widget( array( 'video_type' => 'vimeo', 'vimeo_url' => 'https://vimeo.com/76979871' ), '<iframe src="https://player.vimeo.com/video/76979871"></iframe>' );
		self::assertSame( $vimeo, self::rule()->apply( $vimeo ), 'a real iframe is IframeRule\'s job' );

		$lightbox = str_replace( 'elementor-open-inline', 'elementor-open-lightbox', self::widget( array( 'video_type' => 'youtube', 'youtube_url' => 'https://youtu.be/y_pjE_p1HwE' ), '' ) );
		self::assertSame( $lightbox, self::rule()->apply( $lightbox ), 'lightbox loads nothing before a click on the owner\'s overlay' );

		$hosted = self::widget( array( 'video_type' => 'hosted', 'hosted_url' => array( 'url' => 'https://example.test/wp-content/uploads/clip.mp4' ) ), '<video src="https://example.test/wp-content/uploads/clip.mp4"></video>' );
		self::assertSame( $hosted, self::rule()->apply( $hosted ) );
	}

	public function test_the_owner_who_let_youtube_through_is_obeyed(): void {
		$providers = Descriptors::all();
		foreach ( $providers as &$provider ) {
			if ( 'youtube' === $provider['id'] ) {
				$provider['enabled'] = false;
			}
		}
		unset( $provider );
		$html = self::widget( array( 'video_type' => 'youtube', 'youtube_url' => 'https://youtu.be/y_pjE_p1HwE' ) );
		self::assertSame( $html, self::rule( $providers )->apply( $html ) );
		self::assertStringContainsString( 'cg-embed', self::rule( $providers )->apply( $html, array( 'force_gate' => true ) ), 'the per-block "always" override still wins' );
	}

	public function test_the_should_gate_veto_is_asked_with_the_embed_url(): void {
		$asked = array();
		$veto  = static function ( bool $gate, string $url ) use ( &$asked ): bool {
			$asked[] = $url;
			return false;
		};
		$html = self::widget( array( 'video_type' => 'youtube', 'youtube_url' => 'https://youtu.be/y_pjE_p1HwE' ) );
		self::assertSame( $html, self::rule( null, $veto )->apply( $html ) );
		self::assertSame( array( 'https://www.youtube.com/embed/y_pjE_p1HwE' ), $asked );
	}

	public function test_only_an_own_host_overlay_becomes_the_poster(): void {
		$own = self::widget(
			array( 'video_type' => 'youtube', 'youtube_url' => 'https://youtu.be/y_pjE_p1HwE' ),
			'<div class="elementor-video"></div><div class="elementor-custom-embed-image-overlay" style="background-image: url(https://example.test/wp-content/uploads/cover.jpg);"></div>'
		);
		self::assertStringContainsString( 'cg-embed__poster" src="https://example.test/wp-content/uploads/cover.jpg"', self::rule()->apply( $own ) );

		$foreign = str_replace( 'https://example.test/wp-content/uploads/cover.jpg', 'https://i.ytimg.com/vi/y_pjE_p1HwE/hqdefault.jpg', $own );
		$out     = self::rule()->apply( $foreign );
		self::assertStringContainsString( 'class="cg-embed"', $out );
		self::assertStringNotContainsString( 'i.ytimg.com', $out, 'a provider thumbnail is a third-party request and never a poster (§5.4)' );
	}
}
