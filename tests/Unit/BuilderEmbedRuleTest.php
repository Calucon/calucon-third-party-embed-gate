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

	// ---- Players of the free builders, measured on the real plugins ---------

	/** @dataProvider provider_pages */
	public function test_a_providers_page_as_a_video_source( string $page, ?string $expected ): void {
		self::assertSame( $expected, BuilderEmbedRule::provider_page_embed( $page ) );
	}

	public static function provider_pages(): array {
		return array(
			'youtube watch'     => array( 'https://www.youtube.com/watch?v=y_pjE_p1HwE&_=1', 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			'youtu.be'          => array( 'https://youtu.be/y_pjE_p1HwE', 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			'protocol-relative' => array( '//www.youtube.com/watch?v=y_pjE_p1HwE', 'https://www.youtube.com/embed/y_pjE_p1HwE' ),
			'vimeo'             => array( 'https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871' ),
			'vimeo channel'     => array( 'https://vimeo.com/channels/staffpicks/76979871', 'https://player.vimeo.com/video/76979871' ),
			// Media, or nothing a player is built for: left to be a <video>.
			'a file'            => array( 'https://cdn.example.net/clip.mp4', null ),
			'a file on youtube' => array( 'https://www.youtube.com/not-a-video.jpg', null ),
			'a channel'         => array( 'https://www.youtube.com/@calucon', null ),
			'a lookalike host'  => array( 'https://www.youtube.com.example.net/watch?v=y_pjE_p1HwE', null ),
			'the name in a path' => array( 'https://cdn.example.net/youtube.com/watch?v=y_pjE_p1HwE', null ),
			'vimeo in a path'    => array( 'https://cdn.example.net/vimeo.com/76979871', null ),
			'empty'             => array( '', null ),
		);
	}

	public function test_a_video_element_with_a_provider_page_becomes_a_panel_and_a_file_stays_media(): void {
		$html = '<div style="width: 640px;" class="wp-video"><video class="wp-video-shortcode" width="640" height="360" controls="controls"><source type="video/youtube" src="https://www.youtube.com/watch?v=y_pjE_p1HwE&#038;_=1" /><a href="https://www.youtube.com/watch?v=y_pjE_p1HwE">link</a></video></div>';
		$once = self::rule()->apply( $html );

		self::assertStringNotContainsString( '<video', $once );
		self::assertStringNotContainsString( '<source', $once );
		self::assertStringContainsString( '"src":"https://www.youtube-nocookie.com/embed/y_pjE_p1HwE"', $once );
		self::assertStringContainsString( '"width":"640","height":"360","class":"' . BuilderEmbedRule::FLUID . '"', $once, 'the loaded player is sized by the gate: the <video> that did it is gone' );
		self::assertStringStartsWith( '<div style="width: 640px;" class="wp-video"><div class="cg-embed"', $once );
		self::assertSame( $once, self::rule()->apply( $once ), 'idempotent' );

		foreach ( array(
			'<video src="https://cdn.example.net/clip.mp4" controls></video>',
			'<video controls><source src="https://example.test/wp-content/uploads/clip.webm"><source src="https://cdn.example.net/clip.mp4"></video>',
			'<video poster="https://www.youtube.com/watch?v=y_pjE_p1HwE" src="/clip.mp4"></video>',
		) as $media ) {
			self::assertSame( $media, self::rule()->apply( $media ), $media );
		}

		self::assertSame( $html, self::rule( self::without( 'youtube' ) )->apply( $html ) );
		$asked = array();
		$veto  = static function ( bool $gate, string $url ) use ( &$asked ): bool {
			$asked[] = $url;
			return false;
		};
		self::assertSame( $html, self::rule( null, $veto )->apply( $html ) );
		self::assertSame( array( 'https://www.youtube.com/watch?v=y_pjE_p1HwE&_=1' ), $asked, 'the veto is asked with the address in the markup' );
	}

	public function test_a_plyr_embed_keeps_its_element_and_loses_what_plyr_reads(): void {
		$html = '<div class="wrap"><div id="eaelsv-player-1" data-plyr-provider="youtube" data-plyr-embed-id="y_pjE_p1HwE" data-plyr-config="{&quot;autoplay&quot;:0}"><p>old content</p><div>nested</div></div><span class="close"></span></div>';
		$once = self::rule()->apply( $html );

		// The element and its id stay — scripts address it — and Plyr's two
		// attributes go, so `new Plyr()` finds a plain div.
		self::assertStringContainsString( '<div id="eaelsv-player-1" data-plyr-config="{&quot;autoplay&quot;:0}"><div class="cg-embed"', $once );
		self::assertStringNotContainsString( 'data-plyr-provider', $once );
		self::assertStringNotContainsString( 'data-plyr-embed-id', $once );
		self::assertStringNotContainsString( 'old content', $once );
		self::assertStringEndsWith( '</div></div><span class="close"></span></div>', $once, 'the element is closed where it closed, nested divs and all' );
		self::assertSame( $once, self::rule()->apply( $once ), 'idempotent' );

		// Plyr takes a URL as the id, too.
		self::assertStringContainsString( 'player.vimeo.com/video/76979871', self::rule()->apply( '<div data-plyr-provider="vimeo" data-plyr-embed-id="https://vimeo.com/76979871"></div>' ) );
		self::assertStringContainsString( 'youtube-nocookie.com/embed/y_pjE_p1HwE', self::rule()->apply( '<div data-plyr-provider="YouTube" data-plyr-embed-id="https://youtu.be/y_pjE_p1HwE"></div>' ) );

		foreach ( array(
			'<div data-plyr-provider="html5" data-plyr-embed-id="x"></div>',
			'<div data-plyr-provider="youtube"></div>',
			'<div data-plyr-provider="youtube" data-plyr-embed-id=""></div>',
		) as $other ) {
			self::assertSame( $other, self::rule()->apply( $other ), $other );
		}
		self::assertSame( $html, self::rule( self::without( 'youtube' ) )->apply( $html ) );
	}

	private static function video_box( string $attributes, string $src, string $image = 'transparent' ): string {
		return '<div id="premium-video-box-container-1" class="premium-video-box-container" ' . $attributes . '><div><div class="premium-video-box-inner-wrap"><div data-src="' . htmlspecialchars( $src ) . '" class="premium-video-box-video-container"></div><div style="background-image:' . htmlspecialchars( $image, ENT_QUOTES ) . '" class="premium-video-box-image-container none"></div><div class="premium-video-box-play-icon-container"><svg></svg></div></div></div></div><p>After.</p>';
	}

	public function test_a_premium_addons_video_box_becomes_the_panel_whole(): void {
		$src  = 'https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed&rel=0&autoplay=1';
		$once = self::rule()->apply( self::video_box( 'data-type="youtube" data-thumbnail=""', $src ) );

		// Nothing of the box is left: with its container gone the add-on's
		// script finds no element; a container without its data-src throws.
		self::assertStringNotContainsString( 'premium-video-box', $once );
		self::assertStringContainsString( '"src":"https://www.youtube-nocookie.com/embed/y_pjE_p1HwE?rel=0"', $once );
		self::assertStringEndsWith( '</div><p>After.</p>', $once );
		self::assertStringStartsWith( '<div class="cg-embed"', $once );
		self::assertSame( $once, self::rule()->apply( $once ), 'idempotent' );

		// The owner's own image becomes the poster; the provider's never.
		$own = self::rule()->apply( self::video_box( 'data-type="youtube" data-thumbnail="1"', $src, "url('https://example.test/wp-content/uploads/cover.png')" ) );
		self::assertStringContainsString( 'cg-embed__poster" src="https://example.test/wp-content/uploads/cover.png"', $own );
		$theirs = self::rule()->apply( self::video_box( 'data-type="youtube" data-thumbnail="1"', $src, "url('https://i.ytimg.com/vi/y_pjE_p1HwE/maxresdefault.jpg')" ) );
		self::assertStringContainsString( 'class="cg-embed"', $theirs );
		self::assertStringNotContainsString( 'ytimg', $theirs );
	}

	public function test_a_video_box_that_loads_nothing_by_itself_is_left_alone(): void {
		$src = 'https://www.youtube.com/embed/y_pjE_p1HwE';
		foreach ( array(
			'a file'              => self::video_box( 'data-type="self" data-thumbnail="1"', $src ),
			'the lightbox'        => self::video_box( 'data-lightbox="{&quot;type&quot;:&quot;elementor&quot;}" data-type="youtube"', $src ),
			'own-host player'     => self::video_box( 'data-type="youtube"', 'https://example.test/player/1' ),
			'no address'          => '<div class="premium-video-box-container" data-type="youtube"><div class="premium-video-box-video-container"></div></div>',
			'the owner said no'   => null,
		) as $name => $html ) {
			if ( null === $html ) {
				$html = self::video_box( 'data-type="youtube"', $src );
				self::assertSame( $html, self::rule( self::without( 'youtube' ) )->apply( $html ), $name );
				continue;
			}
			self::assertSame( $html, self::rule()->apply( $html ), $name );
		}
	}

	public function test_backgrounds_that_are_an_element_with_children_go_whole(): void {
		$beaver = '<div class="fl-row-content-wrap"><div class="fl-bg-video" data-fallback="" data-youtube="https://www.youtube.com/watch?v=y_pjE_p1HwE" data-video-id="y_pjE_p1HwE"><div class="fl-bg-video-player"></div><div class="fl-bg-video-play-pause"><span><i></i></span></div></div><div class="fl-row-content"><p>Text.</p></div></div>';
		self::assertSame( '<div class="fl-row-content-wrap"><div class="fl-row-content"><p>Text.</p></div></div>', self::background()->apply( $beaver ) );

		$vimeo = str_replace( 'data-youtube="https://www.youtube.com/watch?v=y_pjE_p1HwE"', 'data-vimeo="https://vimeo.com/76979871"', $beaver );
		self::assertStringNotContainsString( 'fl-bg-video', self::background()->apply( $vimeo ) );
		self::assertSame( $beaver, self::background( self::without( 'youtube' ) )->apply( $beaver ) );
		self::assertStringNotContainsString( 'fl-bg-video', self::background( self::without( 'youtube' ) )->apply( $vimeo ) );

		$file = '<div class="fl-bg-video" data-mp4="https://cdn.example.net/bg.mp4" data-youtube=""><div class="fl-bg-video-play-pause"></div></div>';
		self::assertSame( $file, self::background()->apply( $file ), 'a video file is media' );

		// Visual Composer injects the provider's API the moment its bundle
		// runs, unless an element with its script's id is already there.
		$composer = '<div class="vce-content-background-container"><div class="vce-asset-video-yt-wrapper"><div class="vce-asset-video-yt-background" data-vce-assets-video-yt="y_pjE_p1HwE"><svg width="640" height="360"></svg><div class="vce-asset-video-yt-player"></div></div></div></div>';
		$out      = self::background()->apply( $composer . $composer );
		self::assertStringNotContainsString( 'data-vce-assets-video-yt', $out );
		self::assertSame( 1, substr_count( $out, '<span id="vcv-asset-youtube-iframe-api" hidden></span>' ), 'one stub, not one per row: an id is unique' );
		self::assertSame( $out, self::background()->apply( $out ) );
		$vc_vimeo = str_replace( array( 'video-yt', '"y_pjE_p1HwE"' ), array( 'video-vimeo', '"76979871"' ), $composer );
		self::assertStringContainsString( '<span id="vcv-asset-vimeo-player-api" hidden></span>', self::background()->apply( $vc_vimeo ) );
		$veto = static function (): bool {
			return false;
		};
		self::assertSame( $composer, self::background( null, $veto )->apply( $composer ) );
	}

	public function test_background_frames_in_a_builders_own_wrapper_go_with_it(): void {
		$frame = '<iframe frameborder="0" class="kb-blocks-bg-video youtube" src="https://www.youtube.com/embed/y_pjE_p1HwE?autoplay=1&amp;mute=1" autoplay></iframe>';
		foreach ( array( 'kb-bg-video-iframe kb-bg-video-ratio-16-9', 'fl-bg-embed-code', 'brz-bg-video' ) as $classes ) {
			$html = '<div class="row"><div class="' . $classes . '">' . $frame . '</div><div class="content"><p>Text.</p></div></div>';
			self::assertSame( '<div class="row"><div class="content"><p>Text.</p></div></div>', self::background()->apply( $html ), $classes );
			self::assertStringNotContainsString( 'cg-embed', PipelineFactory::gate( $html ), $classes . ': no panel behind the row' );
		}
		// Parked by a lazy loader: still the address that will be asked for.
		$lazy = '<div class="kb-bg-video-iframe"><iframe class="lazyload" src="about:blank" data-src="https://www.youtube.com/embed/y_pjE_p1HwE"></iframe></div>';
		self::assertSame( '', self::background()->apply( $lazy ) );
		// Not a frame in such a wrapper: the iframe rule's, with a panel.
		$plain = '<div class="kb-video"><iframe src="https://www.youtube.com/embed/y_pjE_p1HwE"></iframe></div>';
		self::assertSame( $plain, self::background()->apply( $plain ) );
		self::assertStringContainsString( 'cg-embed', PipelineFactory::gate( $plain ) );
	}

	public function test_where_an_element_ends(): void {
		$scanner = new HtmlScanner();
		$html    = '<div id="a"><div>one</div><script type="text/html"></div></div></script><div data-x="</div>">two<div>deep</div></div></div><p>after</p>';
		$from    = strlen( '<div id="a">' );
		$close   = $scanner->closing_tag_offset( $html, $from, 'div' );

		self::assertSame( '</div><p>after</p>', substr( $html, (int) $close ) );
		self::assertNull( $scanner->closing_tag_offset( '<div><div>never closed</div>', 5, 'div' ) );
		self::assertSame( 4, $scanner->closing_tag_offset( '<p>x</p><p>y</p>', 3, 'p' ) );
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
