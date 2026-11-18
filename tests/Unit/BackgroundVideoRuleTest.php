<?php
/**
 * BackgroundVideoRule: the edges the fixtures do not reach.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\BackgroundVideoRule;
use CaluconEmbedGate\Detection\HostMatcher;
use CaluconEmbedGate\Detection\HtmlScanner;
use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Tests\Support\PipelineFactory;
use PHPUnit\Framework\TestCase;

final class BackgroundVideoRuleTest extends TestCase {

	private const YT = 'https://www.youtube.com/watch?v=y_pjE_p1HwE';

	private static function rule( ?array $providers = null, ?callable $should_gate = null ): BackgroundVideoRule {
		return new BackgroundVideoRule(
			new HtmlScanner(),
			new HostMatcher( array( 'example.test' ) ),
			new Registry( null === $providers ? Descriptors::all() : $providers ),
			$should_gate
		);
	}

	private static function section( array $settings, string $tag = 'section' ): string {
		return '<' . $tag . ' class="elementor-section" data-settings="' . htmlspecialchars( (string) json_encode( $settings ), ENT_QUOTES ) . '"><div class="elementor-background-video-container"><div class="elementor-background-video-embed"></div></div><p>Content.</p></' . $tag . '>';
	}

	private static function cover( string $src, string $inner_extra = '' ): string {
		return '<div class="wp-block-cover"><div class="wp-block-cover__video-background wp-block-cover__embed-background"><iframe src="' . htmlspecialchars( $src ) . '" title="Background video" allow="autoplay; fullscreen"></iframe>' . $inner_extra . '</div><span class="wp-block-cover__background"></span><div class="wp-block-cover__inner-container"><p>Text.</p></div></div>';
	}

	private static function settings_of( string $html ): array {
		self::assertSame( 1, preg_match( '/data-settings="([^"]*)"/', $html, $m ) );
		$settings = json_decode( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
		self::assertIsArray( $settings, 'the rewritten data-settings must stay parseable, or Elementor cannot read its own settings' );
		return $settings;
	}

	private static function without( string $id ): array {
		$providers = Descriptors::all();
		foreach ( $providers as &$provider ) {
			if ( $id === $provider['id'] ) {
				$provider['enabled'] = false;
			}
		}
		unset( $provider );
		return $providers;
	}

	/** @dataProvider links */
	public function test_the_player_elementor_would_build( string $link, ?string $expected ): void {
		self::assertSame( $expected, BackgroundVideoRule::elementor_player_url( $link ) );
	}

	public static function links(): array {
		return array(
			'youtube watch'         => array( self::YT, 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			'youtube short link'    => array( 'youtu.be/y_pjE_p1HwE', 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			// Elementor loads YouTube's player API for anything on
			// youtube.com, id or not — so this is a player, too.
			'youtube without an id' => array( 'https://www.youtube.com/@calucon', 'https://www.youtube.com/embed/0' ),
			'vimeo'                 => array( 'https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871' ),
			'vimeo in a channel'    => array( 'https://vimeo.com/channels/staffpicks/76979871?h=abc', 'https://player.vimeo.com/video/76979871' ),
			// Elementor's test is indexOf( 'vimeo.com' ): it loads Vimeo's
			// API for this as well.
			'vimeo without an id'   => array( 'https://vimeo.com/calucon', 'https://player.vimeo.com/video/0' ),
			'a video file'          => array( 'https://example.test/wp-content/uploads/loop.mp4', null ),
			'a foreign video file'  => array( 'https://cdn.example.net/loop.mp4', null ),
			'empty'                 => array( '', null ),
		);
	}

	public function test_the_link_is_emptied_and_everything_else_survives(): void {
		$html = self::section(
			array(
				'background_background'     => 'video',
				'background_video_link'     => self::YT,
				'background_video_fallback' => array( 'url' => 'https://example.test/wp-content/uploads/still.jpg', 'id' => 12 ),
				'title'                     => 'a\\z $1',
				'empty_object'              => new \stdClass(),
				'empty_list'                => array(),
			)
		);
		$once = self::rule()->apply( $html );

		self::assertStringNotContainsString( 'youtube', $once );
		self::assertStringContainsString( 'elementor-background-video-container', $once, 'the element and its children stay' );
		self::assertStringContainsString( '<p>Content.</p>', $once );
		$settings = self::settings_of( $once );
		self::assertSame( '', $settings['background_video_link'], 'emptied, so Elementor\'s run() takes the deactivate branch' );
		self::assertSame( 'video', $settings['background_background'] );
		self::assertSame( 'https://example.test/wp-content/uploads/still.jpg', $settings['background_video_fallback']['url'] );
		self::assertSame( 'a\\z $1', $settings['title'] );
		self::assertStringContainsString( '&quot;empty_object&quot;:{}', $once, 'an empty object must not come back as a list' );
		self::assertStringContainsString( '&quot;empty_list&quot;:[]', $once );

		self::assertSame( $once, self::rule()->apply( $once ), 'idempotent' );
	}

	public function test_every_tag_a_container_can_be_rendered_as(): void {
		foreach ( array( 'div', 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'a' ) as $tag ) {
			$out = self::rule()->apply( self::section( array( 'background_background' => 'video', 'background_video_link' => self::YT ), $tag ) );
			self::assertStringNotContainsString( 'youtube', $out, "<$tag>" );
		}
	}

	public function test_what_is_not_a_third_party_player_is_left_byte_identical(): void {
		$cases = array(
			'a video file'           => self::section( array( 'background_background' => 'video', 'background_video_link' => 'https://cdn.example.net/loop.mp4' ) ),
			'not a video background' => self::section( array( 'background_background' => 'classic', 'background_video_link' => self::YT ) ),
			'no background setting'  => self::section( array( 'background_video_link' => self::YT ) ),
			'link is not a string'   => self::section( array( 'background_background' => 'video', 'background_video_link' => array( 'url' => self::YT ) ) ),
			'settings are not JSON'  => '<section data-settings="background_video_link youtube.com">x</section>',
			'the words in text'      => '<p>Set background_video_link to https://www.youtube.com/watch?v=y_pjE_p1HwE</p>',
		);
		foreach ( $cases as $name => $html ) {
			self::assertSame( $html, self::rule()->apply( $html ), $name );
		}
	}

	public function test_the_owner_who_let_the_provider_through_is_obeyed(): void {
		$youtube = self::section( array( 'background_background' => 'video', 'background_video_link' => self::YT ) );
		$vimeo   = self::section( array( 'background_background' => 'video', 'background_video_link' => 'https://vimeo.com/76979871' ) );

		self::assertSame( $youtube, self::rule( self::without( 'youtube' ) )->apply( $youtube ) );
		self::assertStringNotContainsString( 'vimeo.com', self::rule( self::without( 'youtube' ) )->apply( $vimeo ), 'letting YouTube through says nothing about Vimeo' );
		self::assertStringNotContainsString( 'youtube', self::rule( self::without( 'youtube' ) )->apply( $youtube, array( 'force_gate' => true ) ), 'an explicit "always" still wins' );

		$cover = self::cover( 'https://www.youtube.com/embed/y_pjE_p1HwE?autoplay=1&mute=1' );
		self::assertSame( $cover, self::rule( self::without( 'youtube' ) )->apply( $cover ) );
	}

	public function test_the_should_gate_veto_is_asked_with_what_the_markup_names(): void {
		$asked = array();
		$veto  = static function ( bool $gate, string $url ) use ( &$asked ): bool {
			$asked[] = $url;
			return false;
		};
		$section = self::section( array( 'background_background' => 'video', 'background_video_link' => self::YT ) );
		$cover   = self::cover( 'https://www.youtube.com/embed/y_pjE_p1HwE?autoplay=1' );

		self::assertSame( $section, self::rule( null, $veto )->apply( $section ) );
		self::assertSame( $cover, self::rule( null, $veto )->apply( $cover ) );
		self::assertSame( array( self::YT, 'https://www.youtube.com/embed/y_pjE_p1HwE?autoplay=1' ), $asked );
	}

	public function test_a_cover_background_goes_with_its_wrapper_and_nothing_else(): void {
		$out = self::rule()->apply( self::cover( 'https://www.youtube.com/embed/y_pjE_p1HwE?autoplay=1&amp;mute=1' ) );

		self::assertSame( '<div class="wp-block-cover"><span class="wp-block-cover__background"></span><div class="wp-block-cover__inner-container"><p>Text.</p></div></div>', $out );
	}

	public function test_a_cover_wrapper_that_is_not_cores_markup_falls_to_the_iframe_rule(): void {
		// Something besides the one iframe in the wrapper: not what core
		// writes, so nothing is removed here — and the iframe rule, next in
		// line, gates it like any other. Never a pass-through.
		$html = self::cover( 'https://www.youtube.com/embed/y_pjE_p1HwE', '<p>More.</p>' );
		self::assertSame( $html, self::rule()->apply( $html ) );

		$gated = PipelineFactory::gate( $html );
		self::assertStringNotContainsString( '<iframe', $gated );
		self::assertStringContainsString( 'class="cg-embed"', $gated );

		$before = str_replace( '<iframe', '<p>First.</p><iframe', self::cover( 'https://www.youtube.com/embed/y_pjE_p1HwE' ) );
		self::assertSame( $before, self::rule()->apply( $before ) );
	}

	public function test_an_unknown_third_party_cover_background_is_removed_too(): void {
		// Invariant 6: by host, not by a list of providers.
		$out = self::rule()->apply( self::cover( 'https://player.example.net/embed/42' ) );
		self::assertStringNotContainsString( 'example.net', $out );
		self::assertStringNotContainsString( '<iframe', $out );
	}

	public function test_the_whole_pipeline_leaves_no_panel_behind_a_cover(): void {
		$out = PipelineFactory::gate( self::cover( 'https://www.youtube.com/embed/y_pjE_p1HwE?autoplay=1' ) );
		self::assertStringNotContainsString( 'cg-embed', $out, 'a panel under the overlay is one nobody can press' );
		self::assertStringNotContainsString( 'youtube', $out );
	}
}
