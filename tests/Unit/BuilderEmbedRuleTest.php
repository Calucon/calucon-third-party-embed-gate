<?php
/**
 * BuilderEmbedRule and the theme backgrounds in BackgroundVideoRule: the
 * edges the fixtures do not reach.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\BackgroundVideoRule;
use CaluconEmbedGate\Detection\BuilderEmbedRule;
use CaluconEmbedGate\Detection\HostMatcher;
use CaluconEmbedGate\Detection\HtmlScanner;
use CaluconEmbedGate\Detection\IframeRule;
use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Rendering\PlaceholderRenderer;
use CaluconEmbedGate\Tests\Support\PipelineFactory;
use PHPUnit\Framework\TestCase;

final class BuilderEmbedRuleTest extends TestCase {

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

	private static function rule( ?array $providers = null, ?callable $should_gate = null ): BuilderEmbedRule {
		$scanner  = new HtmlScanner();
		$hosts    = new HostMatcher( array( 'example.test' ) );
		$registry = new Registry( null === $providers ? Descriptors::all() : $providers );
		$renderer = new PlaceholderRenderer();
		return new BuilderEmbedRule( $scanner, $hosts, $registry, $renderer, new IframeRule( $scanner, $hosts, $registry, $renderer, $should_gate ), $should_gate );
	}

	private static function background( ?array $providers = null, ?callable $should_gate = null ): BackgroundVideoRule {
		return new BackgroundVideoRule( new HtmlScanner(), new HostMatcher( array( 'example.test' ) ), new Registry( null === $providers ? Descriptors::all() : $providers ), $should_gate );
	}

	private static function enfold( string $classes, string $src, string $after = '' ): string {
		return "<div class='avia-video avia-video-16-9 $classes' data-original_url='http://www.youtube.com/watch?v=y_pjE_p1HwE'><script type='text/html' class='av-video-tmpl'><div class='avia-iframe-wrap'><iframe width=\"1500\" height=\"844\" src=\"$src\" frameborder=\"0\" allowfullscreen></iframe></div></script><div class='av-click-to-play-overlay'><div class=\"avia_playpause_icon\"></div></div></div>$after";
	}

	private const IMMEDIATE = 'av-lazyload-immediate av-lazyload-video-embed';
	private const YT        = 'https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed&autoplay=0';

	public function test_an_enfold_video_becomes_a_panel_and_leaves_enfold_nothing_to_click(): void {
		$once = self::rule()->apply( self::enfold( self::IMMEDIATE, self::YT, '<p>After.</p>' ) );

		self::assertStringContainsString( 'class="cg-embed"', $once );
		self::assertStringContainsString( 'www.youtube-nocookie.com/embed/y_pjE_p1HwE', $once );
		// The two things Enfold's script acts on are gone: the template it
		// would write into the element, and the overlay it clicks by itself.
		self::assertStringNotContainsString( 'av-video-tmpl', $once );
		self::assertStringNotContainsString( 'av-click-to-play-overlay', $once );
		self::assertStringNotContainsString( '<iframe', $once );
		// The element stays (Enfold sizes the loaded player with it), and
		// the panel is its direct child — not inside avia-iframe-wrap, which
		// Enfold positions absolutely.
		self::assertSame( 1, preg_match( "#^<div class='avia-video [^>]*><div class=\"cg-embed\"#", $once ) );
		self::assertStringNotContainsString( 'avia-iframe-wrap', $once );
		self::assertStringEndsWith( '</div></div><p>After.</p>', $once, 'what follows the element is untouched' );

		self::assertSame( $once, self::rule()->apply( $once ), 'idempotent' );
	}

	public function test_enfolds_own_click_to_play_and_an_own_host_video_are_left_alone(): void {
		// Without "immediate", Enfold waits for a click on its preview image.
		$own_design = self::enfold( 'av-lazyload-video-embed', self::YT );
		self::assertSame( $own_design, self::rule()->apply( $own_design ) );

		$own_host = self::enfold( self::IMMEDIATE, 'https://example.test/player/clip' );
		self::assertSame( $own_host, self::rule()->apply( $own_host ) );

		$not_enfold = '<div class="avia-video av-lazyload-immediate"><p>No template here.</p></div>';
		self::assertSame( $not_enfold, self::rule()->apply( $not_enfold ) );

		$empty_template = "<div class='avia-video av-lazyload-immediate'><script type='text/html' class='av-video-tmpl'><p>nothing</p></script></div>";
		self::assertSame( $empty_template, self::rule()->apply( $empty_template ) );
	}

	public function test_an_enfold_video_obeys_the_owner_and_the_veto(): void {
		$html = self::enfold( self::IMMEDIATE, self::YT );
		self::assertSame( $html, self::rule( self::without( 'youtube' ) )->apply( $html ) );
		self::assertStringContainsString( 'cg-embed', self::rule( self::without( 'youtube' ) )->apply( $html, array( 'force_gate' => true ) ) );

		$asked = array();
		$veto  = static function ( bool $gate, string $url ) use ( &$asked ): bool {
			$asked[] = $url;
			return false;
		};
		self::assertSame( $html, self::rule( null, $veto )->apply( $html ) );
		self::assertSame( array( self::YT ), $asked );
	}

	public function test_two_enfold_videos_and_what_sits_between_them(): void {
		$html = self::enfold( self::IMMEDIATE, self::YT ) . '<div class="between"><div>nested</div></div>' . self::enfold( self::IMMEDIATE, 'https://player.vimeo.com/video/76979871' );
		$out  = self::rule()->apply( $html );

		self::assertSame( 2, substr_count( $out, 'class="cg-embed"' ) );
		self::assertStringContainsString( '</div></div><div class="between"><div>nested</div></div><div class=\'avia-video', $out );
	}

	public function test_the_closing_tag_of_the_element_is_not_one_inside_its_template(): void {
		// The template is script text: its </div> closes nothing in the
		// document. A count over the raw bytes would end the element early
		// and leave the overlay — Enfold's trigger — behind.
		$html = "<div class='avia-video av-lazyload-immediate'><script type='text/html' class='av-video-tmpl'></div></div><iframe src=\"" . self::YT . "\"></iframe></script><div class='av-click-to-play-overlay'></div></div><p>After.</p>";
		$out  = self::rule()->apply( $html );

		self::assertStringNotContainsString( 'av-click-to-play-overlay', $out );
		self::assertStringEndsWith( '</div></div><p>After.</p>', $out );
	}

	/** @dataProvider facades */
	public function test_the_embed_a_facade_element_stands_for( string $tag, array $attributes, ?string $expected ): void {
		self::assertSame( $expected, BuilderEmbedRule::facade_url( $tag, $attributes ) );
	}

	public static function facades(): array {
		return array(
			'youtube'            => array( 'lite-youtube', array( 'videoid' => 'y_pjE_p1HwE' ), 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			'youtube, padded'    => array( 'lite-youtube', array( 'videoid' => ' y_pjE_p1HwE ' ), 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			'youtube playlist'   => array( 'lite-youtube', array( 'playlistid' => 'PLexample0001' ), 'https://www.youtube.com/embed/videoseries?list=PLexample0001' ),
			'video wins'         => array( 'lite-youtube', array( 'videoid' => 'AbCdEfGhIjK', 'playlistid' => 'PLexample0001' ), 'https://www.youtube.com/embed/AbCdEfGhIjK' ),
			'a hostile id'       => array( 'lite-youtube', array( 'videoid' => 'x"/><script>' ), 'https://www.youtube.com/embed/x%22%2F%3E%3Cscript%3E' ),
			'vimeo'              => array( 'lite-vimeo', array( 'videoid' => '76979871' ), 'https://player.vimeo.com/video/76979871' ),
			'nothing named'      => array( 'lite-youtube', array( 'class' => 'x' ), null ),
			'empty id'           => array( 'lite-youtube', array( 'videoid' => '  ' ), null ),
			'vimeo has no lists' => array( 'lite-vimeo', array( 'playlistid' => 'x' ), null ),
			'id is not a text'   => array( 'lite-youtube', array( 'videoid' => array( 'x' ) ), null ),
		);
	}

	public function test_a_facade_element_becomes_a_panel_with_its_fallback_content_consumed(): void {
		$html = '<p>a</p><lite-youtube videoid="y_pjE_p1HwE" playlabel="Play: Kolkja" style="background-image:url(https://i.ytimg.com/vi/y_pjE_p1HwE/hqdefault.jpg)"><a href="https://youtube.com/watch?v=y_pjE_p1HwE" class="lty-playbtn">Play</a></lite-youtube><p>b</p>';
		$once = self::rule()->apply( $html );

		self::assertStringNotContainsString( 'lite-youtube', $once );
		self::assertStringNotContainsString( 'ytimg', $once, 'the thumbnail the component would paint is a request to the provider' );
		self::assertStringNotContainsString( 'lty-playbtn', $once );
		self::assertStringContainsString( '"title":"Play: Kolkja"', $once );
		self::assertStringContainsString( 'href="https://www.youtube.com/watch?v=y_pjE_p1HwE"', $once );
		self::assertStringStartsWith( '<p>a</p><div class="cg-embed"', $once );
		self::assertStringEndsWith( '</div><p>b</p>', $once );
		self::assertSame( $once, self::rule()->apply( $once ), 'idempotent' );
	}

	public function test_a_facade_obeys_the_owner_and_the_veto_and_another_element_is_not_a_facade(): void {
		$html = '<lite-youtube videoid="y_pjE_p1HwE"></lite-youtube>';
		self::assertSame( $html, self::rule( self::without( 'youtube' ) )->apply( $html ) );
		self::assertStringContainsString( 'cg-embed', self::rule( self::without( 'youtube' ) )->apply( $html, array( 'force_gate' => true ) ) );

		$asked = array();
		$veto  = static function ( bool $gate, string $url ) use ( &$asked ): bool {
			$asked[] = $url;
			return false;
		};
		self::assertSame( $html, self::rule( null, $veto )->apply( $html ) );
		self::assertSame( array( 'https://www.youtube.com/embed/y_pjE_p1HwE' ), $asked );

		foreach ( array(
			'<priv-fac-lite-youtube videoid="y_pjE_p1HwE" data-privacy-type="youtube"></priv-fac-lite-youtube>',
			'<lite-youtube-notes videoid="y_pjE_p1HwE"></lite-youtube-notes>',
			'<p>&lt;lite-youtube videoid="y_pjE_p1HwE"&gt; in a tutorial</p>',
			'<div data-html="<lite-youtube videoid=y_pjE_p1HwE>">in an attribute</div>',
		) as $other ) {
			self::assertSame( $other, self::rule()->apply( $other ), $other );
		}
	}

	// ---- Theme backgrounds (BackgroundVideoRule) ----------------------------

	/** @dataProvider wpbakery_links */
	public function test_the_player_wpbakery_would_build( string $link, ?string $expected ): void {
		self::assertSame( $expected, BackgroundVideoRule::wpbakery_player_url( $link ) );
	}

	public static function wpbakery_links(): array {
		return array(
			'watch'        => array( 'https://www.youtube.com/watch?v=y_pjE_p1HwE', 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			'short'        => array( 'youtu.be/y_pjE_p1HwE', 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			// Their pattern takes whatever follows the slash as the id.
			'a channel'    => array( 'https://www.youtube.com/@calucon', 'https://www.youtube.com/embed/%40calucon' ),
			'a video file' => array( 'https://example.test/loop.mp4', null ),
			'vimeo'        => array( 'https://vimeo.com/76979871', null ),
			'empty'        => array( '', null ),
		);
	}

	public function test_a_wpbakery_background_loses_only_its_attribute(): void {
		$html = '<div class="vc_row" data-vc-full-width="true" data-vc-video-bg=\'https://www.youtube.com/watch?v=y_pjE_p1HwE\' data-vc-parallax="1.5"><p>Content.</p></div>';
		$once = self::background()->apply( $html );

		self::assertSame( '<div class="vc_row" data-vc-full-width="true" data-vc-parallax="1.5"><p>Content.</p></div>', $once );
		self::assertSame( $once, self::background()->apply( $once ) );

		self::assertSame( $html, self::background( self::without( 'youtube' ) )->apply( $html ) );
		$veto = static function (): bool {
			return false;
		};
		self::assertSame( $html, self::background( null, $veto )->apply( $html ) );
		$text = '<p>Set data-vc-video-bg="https://youtu.be/y_pjE_p1HwE" on the row.</p>';
		self::assertSame( $text, self::background()->apply( $text ) );
	}

	public function test_theme_player_stubs_are_removed_and_only_those(): void {
		$flatsome = '<div class="banner-bg"><div class="bg"></div><div class="ux-youtube fill" id="p" data-videoid="y_pjE_p1HwE" data-loop="1"></div><div class="overlay"></div></div>';
		self::assertSame( '<div class="banner-bg"><div class="bg"></div><div class="overlay"></div></div>', self::background()->apply( $flatsome ) );

		$enfold = "<div class='mejs-mediaelement'><div data-src='//player.vimeo.com/video/76979871?api=1' class='av_vimeo_frame' id='p1'></div></div><div class='mejs-mediaelement'><div class='av_youtube_frame' data-videoid='y_pjE_p1HwE'> </div></div>";
		self::assertSame( "<div class='mejs-mediaelement'></div><div class='mejs-mediaelement'></div>", self::background()->apply( $enfold ) );

		foreach ( array(
			'no id'                 => '<div class="ux-youtube"></div>',
			'not empty'             => '<div class="ux-youtube" data-videoid="y_pjE_p1HwE"><p>Text someone put there.</p></div>',
			'another class'         => '<div class="ux-youtube-wrap" data-videoid="y_pjE_p1HwE"></div>',
			'own host frame'        => "<div class='av_vimeo_frame' data-src='https://example.test/video/1'></div>",
			'vimeo frame, no src'   => "<div class='av_vimeo_frame' data-videoid='76979871'></div>",
			'a data-videoid elsewhere' => '<div class="gallery-item" data-videoid="y_pjE_p1HwE"></div>',
		) as $name => $html ) {
			self::assertSame( $html, self::background()->apply( $html ), $name );
		}

		self::assertSame( $flatsome, self::background( self::without( 'youtube' ) )->apply( $flatsome ) );
		self::assertNotSame( $enfold, self::background( self::without( 'youtube' ) )->apply( $enfold ), 'letting YouTube through says nothing about Vimeo' );
	}

	// ---- Avada's lazy frames (IframeRule) -----------------------------------

	public function test_avadas_lazy_frame_is_gated_and_its_consent_placeholder_is_not_doubled(): void {
		$lazy = '<iframe class="lazyload" src="data:image/svg+xml,%3Csvg%3E%3C%2Fsvg%3E" data-orig-src="https://player.vimeo.com/video/76979871?autoplay=1" width="1160" height="653"></iframe>';
		$out  = PipelineFactory::gate( $lazy );
		self::assertStringContainsString( 'class="cg-embed"', $out );
		self::assertStringContainsString( 'player.vimeo.com/video/76979871', $out );
		self::assertStringNotContainsString( 'autoplay', $out );

		// Avada's own consent option: src empty, URL parked, their button next.
		$theirs = '<iframe data-privacy-type="gmaps" src="" class="fusion-hidden lazyload" data-privacy-src="https://www.google.com/maps/embed/v1/place?key=K" data-orig-src="https://www.google.com/maps/embed/v1/place?key=K"></iframe>';
		self::assertSame( $theirs, PipelineFactory::gate( $theirs ) );

		// The exemption is for a frame that requests nothing. One that
		// carries the attributes AND a live src is still an embed.
		$live = '<iframe data-privacy-type="gmaps" src="https://www.google.com/maps/embed/v1/place?key=K" data-privacy-src="https://www.google.com/maps/embed/v1/place?key=K"></iframe>';
		self::assertStringContainsString( 'class="cg-embed"', PipelineFactory::gate( $live ) );
		// And the type alone, without a parked URL, exempts nothing.
		$half = '<iframe data-privacy-type="gmaps" data-orig-src="https://www.google.com/maps/embed/v1/place?key=K"></iframe>';
		self::assertStringContainsString( 'class="cg-embed"', PipelineFactory::gate( $half ) );
	}
}
