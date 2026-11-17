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

	public function test_a_watch_url_that_is_not_a_page_never_becomes_the_fallback_link(): void {
		// The id is read from anywhere in the string, so this still gates a
		// player — and the link must be the embed URL, never the setting.
		// JSON "\t" decodes to a real tab, which browsers strip from a URL:
		// `java<TAB>script:` IS javascript: to the browser.
		$html = self::widget( array( 'video_type' => 'youtube', 'youtube_url' => "java\tscript:alert(1)//youtu.be/y_pjE_p1HwE" ) );
		$out  = self::rule()->apply( $html );

		self::assertStringContainsString( 'class="cg-embed"', $out );
		self::assertStringNotContainsString( 'script:', $out );
		self::assertStringContainsString( 'href="https://www.youtube.com/embed/y_pjE_p1HwE"', $out );
	}

	public function test_surviving_settings_with_a_backslash_are_still_valid_json_after_the_rewrite(): void {
		$html = self::widget( array( 'video_type' => 'youtube', 'youtube_url' => 'https://youtu.be/y_pjE_p1HwE', 'title' => 'a\\z $1' ) );
		$out  = self::rule()->apply( $html );

		self::assertSame( 1, preg_match( '/data-settings="([^"]*)"/', $out, $m ) );
		$settings = json_decode( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
		self::assertIsArray( $settings, 'the rewritten data-settings must stay parseable, or Elementor cannot read its own settings' );
		self::assertSame( 'a\\z $1', $settings['title'] );
		self::assertSame( ElementorVideoRule::GATED_TYPE, $settings['video_type'] );
	}

	// ---- The atomic element (Elementor 4, data-e-type="e-youtube") --------

	private static function atomic( array $settings, string $inner = '' ): string {
		return '<div data-id="cgat1" data-interaction-id="cgat1" data-e-type="e-youtube" class="e-youtube-base e-default-div" data-settings="' . htmlspecialchars( (string) json_encode( $settings ), ENT_QUOTES ) . '">' . $inner . '</div>';
	}

	private static function atomic_settings( string $html ): array {
		self::assertSame( 1, preg_match( '/data-settings="([^"]*)"/', $html, $m ) );
		$settings = json_decode( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
		self::assertIsArray( $settings, 'the rewritten data-settings must stay parseable — Elementor calls JSON.parse() on it' );
		return $settings;
	}

	public function test_an_atomic_element_is_gated_and_its_handler_stands_down(): void {
		$html = self::atomic( array( 'source' => 'https://www.youtube.com/watch?v=y_pjE_p1HwE', 'autoplay' => true, 'start' => null, 'title' => 'a\\z $1' ) );
		$once = self::rule()->apply( $html );

		self::assertStringContainsString( 'class="cg-embed"', $once );
		self::assertStringContainsString( 'www.youtube-nocookie.com/embed/y_pjE_p1HwE', $once );
		self::assertStringContainsString( 'href="https://www.youtube.com/watch?v=y_pjE_p1HwE"', $once );
		self::assertStringNotContainsString( '<iframe', $once );

		$settings = self::atomic_settings( $once );
		// Emptied, never removed: the handler calls source.match(), and a
		// missing key throws inside Elementor's own script.
		self::assertArrayHasKey( 'source', $settings );
		self::assertSame( '', $settings['source'] );
		self::assertTrue( $settings['autoplay'], 'unrelated settings survive' );
		self::assertNull( $settings['start'] );
		self::assertSame( 'a\\z $1', $settings['title'] );

		self::assertSame( $once, self::rule()->apply( $once ), 'idempotent' );
	}

	/** @dataProvider atomic_sources */
	public function test_whatever_elementors_handler_would_play_is_gated( string $source, string $expected_src ): void {
		$out = self::rule()->apply( self::atomic( array( 'source' => $source ) ) );

		self::assertStringContainsString( '"src":"' . $expected_src . '"', $out );
		self::assertSame( '', self::atomic_settings( $out )['source'] );
	}

	public static function atomic_sources(): array {
		// The last three are URLs only Elementor's own pattern reads
		// (youtube-handler.js). It loads YouTube's player API for each of
		// them, so each must be gated, tidy or not.
		return array(
			'watch'               => array( 'https://www.youtube.com/watch?v=y_pjE_p1HwE', 'https://www.youtube-nocookie.com/embed/y_pjE_p1HwE' ),
			'no scheme'           => array( 'youtu.be/y_pjE_p1HwE', 'https://www.youtube-nocookie.com/embed/y_pjE_p1HwE' ),
			'mobile host'         => array( 'https://m.youtube.com/watch?v=y_pjE_p1HwE', 'https://www.youtube-nocookie.com/embed/y_pjE_p1HwE' ),
			'the /vi/ path'       => array( 'https://www.youtube.com/vi/AbCdEfGhIjK', 'https://www.youtube-nocookie.com/embed/AbCdEfGhIjK' ),
			'the vi= parameter'   => array( 'https://www.youtube.com/watch?vi=AbCdEfGhIjK', 'https://www.youtube-nocookie.com/embed/AbCdEfGhIjK' ),
			// Not an id the YouTube descriptor recognises, so the panel is the
			// generic one for www.youtube.com — gated all the same, which is
			// the point (invariant 6).
			'an id only they see' => array( 'https://youtu.be/AbC.d', 'https://www.youtube.com/embed/AbC.d' ),
		);
	}

	public function test_an_atomic_element_elementor_would_not_play_is_left_alone(): void {
		foreach ( array(
			'empty source'         => array( 'source' => '' ),
			'not a YouTube URL'    => array( 'source' => 'https://example.test/media/clip' ),
			'YouTube, not a video' => array( 'source' => 'https://www.youtube.com/@calucon' ),
			'source is not a text' => array( 'source' => array( 'url' => 'https://youtu.be/y_pjE_p1HwE' ) ),
			'no source at all'     => array( 'autoplay' => true ),
		) as $name => $settings ) {
			$html = self::atomic( $settings );
			self::assertSame( $html, self::rule()->apply( $html ), $name );
		}

		$other = str_replace( 'data-e-type="e-youtube"', 'data-e-type="e-heading"', self::atomic( array( 'source' => 'https://youtu.be/y_pjE_p1HwE' ) ) );
		self::assertSame( $other, self::rule()->apply( $other ), 'another element type that happens to carry a source' );
	}

	public function test_the_atomic_element_obeys_the_owner_the_veto_and_the_always_override(): void {
		$providers = Descriptors::all();
		foreach ( $providers as &$provider ) {
			if ( 'youtube' === $provider['id'] ) {
				$provider['enabled'] = false;
			}
		}
		unset( $provider );
		$html = self::atomic( array( 'source' => 'https://youtu.be/y_pjE_p1HwE' ) );
		self::assertSame( $html, self::rule( $providers )->apply( $html ) );
		self::assertStringContainsString( 'cg-embed', self::rule( $providers )->apply( $html, array( 'force_gate' => true ) ) );

		$asked = array();
		$veto  = static function ( bool $gate, string $url ) use ( &$asked ): bool {
			$asked[] = $url;
			return false;
		};
		self::assertSame( $html, self::rule( null, $veto )->apply( $html ) );
		self::assertSame( array( 'https://www.youtube.com/embed/y_pjE_p1HwE' ), $asked );
	}

	public function test_an_atomic_source_that_is_not_a_page_never_becomes_the_fallback_link(): void {
		$out = self::rule()->apply( self::atomic( array( 'source' => "java\tscript:alert(1)//youtu.be/y_pjE_p1HwE" ) ) );

		self::assertStringContainsString( 'class="cg-embed"', $out );
		self::assertStringNotContainsString( 'script:', $out );
		self::assertStringContainsString( 'href="https://www.youtube.com/embed/y_pjE_p1HwE"', $out );
	}

	public function test_an_atomic_and_a_classic_widget_on_one_page_are_both_gated(): void {
		$html = self::atomic( array( 'source' => 'https://youtu.be/AbCdEfGhIjK' ) )
			. self::widget( array( 'video_type' => 'youtube', 'youtube_url' => 'https://youtu.be/y_pjE_p1HwE' ) );
		$out  = self::rule()->apply( $html );

		self::assertSame( 2, substr_count( $out, 'class="cg-embed"' ) );
		self::assertStringContainsString( 'embed/AbCdEfGhIjK', $out );
		self::assertStringContainsString( 'embed/y_pjE_p1HwE', $out );
	}
}
