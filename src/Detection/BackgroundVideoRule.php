<?php
/**
 * Background videos: a third-party player used as decoration.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Providers\Registry;

/**
 * Removes third-party background videos. No panel, on purpose.
 *
 * A background video sits BEHIND the page's content: an overlay and the
 * section's own text are painted on top of it and take every pointer
 * event. A panel put there is one nobody can use — measured on WordPress
 * 7.2's Cover block, the "Load" button and the link were under the overlay
 * and reachable by keyboard only, and after loading the player sat there
 * paused, because the gate never autoplays (invariant 8) and nothing can
 * be clicked through the overlay to start it. So there is no honest
 * two-click for a decoration: the embed is taken out, and the section keeps
 * whatever the owner gave it besides the video — its overlay colour, its
 * fallback image.
 *
 * Two shapes, both found by measuring a real site:
 *
 * WordPress's Cover block (7.0+, "embed-video" background). Core's render
 * callback asks oEmbed for the player and writes
 *
 *   <div class="wp-block-cover__video-background
 *   wp-block-cover__embed-background" …><iframe src="https://www.youtube
 *   .com/embed/…?autoplay=1&mute=1…" …></iframe></div>
 *
 * The wrapper goes with its iframe: it carries nothing else, and from 7.2
 * it is an Interactivity API region whose context repeats the player URLs.
 *
 * Elementor's section and container backgrounds. Nothing in the HTML but a
 * setting:
 *
 *   <section … data-settings='{"background_background":"video",
 *   "background_video_link":"https://www.youtube.com/watch?v=…"}'>
 *     <div class="elementor-background-video-container">
 *       <div class="elementor-background-video-embed"></div></div>
 *
 * and Elementor's own script (background-video.js) loads YouTube's or
 * Vimeo's player API and builds the player — 30 to 39 third-party requests
 * before any click, with or without whole-page gating (measured
 * 2026-11-18, Elementor 4.4.1). The link is emptied and the handler then
 * does nothing: it runs only when `background_video_link` is set.
 *
 * Scope: the two providers Elementor builds a player for. A link to a
 * video FILE becomes a <video> element, which is media, not an embed — the
 * same line the rest of the plugin draws.
 *
 * WordPress-free: strings and arrays in, strings out.
 */
final class BackgroundVideoRule {

	/** Tags an Elementor section or container can be rendered as. */
	private const ELEMENTOR_TAGS = array( 'div', 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'a' );

	/** @var HtmlScanner */
	private $scanner;

	/** @var HostMatcher */
	private $hosts;

	/** @var Registry */
	private $providers;

	/** @var callable|null Bridge for calucon_embed_gate_should_gate. */
	private $should_gate;

	/**
	 * @param HtmlScanner   $scanner     Tag reader.
	 * @param HostMatcher   $hosts       Host classifier.
	 * @param Registry      $providers   Provider registry.
	 * @param callable|null $should_gate Owner veto (filter bridge).
	 */
	public function __construct( HtmlScanner $scanner, HostMatcher $hosts, Registry $providers, ?callable $should_gate = null ) {
		$this->scanner     = $scanner;
		$this->hosts       = $hosts;
		$this->providers   = $providers;
		$this->should_gate = $should_gate;
	}

	/**
	 * Remove every third-party background video in the fragment.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function apply( string $html, array $ctx = array() ): string {
		// Cheap probes before any parsing (PLAN.md §9.16).
		if ( false !== strpos( $html, 'wp-block-cover__embed-background' ) ) {
			$html = $this->strip_cover_backgrounds( $html, $ctx );
		}
		if ( false !== strpos( $html, 'background_video_link' ) ) {
			$html = $this->empty_elementor_backgrounds( $html, $ctx );
		}
		return $html;
	}

	/**
	 * The Cover block's embedded background: the wrapper and its iframe.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	private function strip_cover_backgrounds( string $html, array $ctx ): string {
		$divs = $this->scanner->find_tags( $html, 'div' );

		// Back to front so earlier offsets stay valid.
		for ( $i = count( $divs ) - 1; $i >= 0; $i-- ) {
			$wrapper = $divs[ $i ];
			if ( ! self::has_class( $wrapper['attributes'], 'wp-block-cover__embed-background' ) ) {
				continue;
			}
			$open_end = $this->scanner->start_tag_end( $html, $wrapper['start'] );
			if ( null === $open_end ) {
				continue;
			}
			// The wrapper holds exactly one thing, the iframe core wrote.
			// Anything else in there is not core's markup: leave it to the
			// iframe rule, which gates whatever it finds.
			$iframe = $this->iframe_at( $html, $open_end );
			if ( null === $iframe || ! preg_match( '#\G\s*</div\s*>#i', $html, $close, 0, $iframe['end'] ) ) {
				continue;
			}
			$src = isset( $iframe['attributes']['src'] ) && is_string( $iframe['attributes']['src'] ) ? trim( $iframe['attributes']['src'] ) : '';
			if ( ! $this->is_gated( $src, $src, $ctx ) ) {
				continue;
			}
			$html = substr( $html, 0, $wrapper['start'] ) . substr( $html, $iframe['end'] + strlen( $close[0] ) );
		}

		return $html;
	}

	/**
	 * Elementor's background video setting: emptied, so its handler stands
	 * down. The element and everything in it stays.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	private function empty_elementor_backgrounds( string $html, array $ctx ): string {
		foreach ( self::ELEMENTOR_TAGS as $tag_name ) {
			if ( false === stripos( $html, '<' . $tag_name ) ) {
				continue;
			}
			$tags = $this->scanner->find_tags( $html, $tag_name );
			for ( $i = count( $tags ) - 1; $i >= 0; $i-- ) {
				$attrs = $tags[ $i ]['attributes'];
				if ( ! isset( $attrs['data-settings'] ) || ! is_string( $attrs['data-settings'] )
					|| false === strpos( $attrs['data-settings'], 'background_video_link' ) ) {
					continue;
				}
				// Objects, not arrays: Elementor's settings nest, and an empty
				// object must come back out as {} — as an array it would be [].
				$settings = json_decode( $attrs['data-settings'] );
				if ( ! is_object( $settings )
					|| ! isset( $settings->background_background, $settings->background_video_link )
					|| 'video' !== $settings->background_background
					|| ! is_string( $settings->background_video_link ) ) {
					continue;
				}
				$link      = trim( $settings->background_video_link );
				$canonical = self::elementor_player_url( $link );
				if ( null === $canonical || ! $this->is_gated( $link, $canonical, $ctx ) ) {
					continue;
				}

				$open_end = $this->scanner->start_tag_end( $html, $tags[ $i ]['start'] );
				if ( null === $open_end ) {
					continue;
				}
				$settings->background_video_link = '';
				$tag                             = substr( $html, $tags[ $i ]['start'], $open_end - $tags[ $i ]['start'] );
				$rewrite                         = ElementorVideoRule::replace_data_settings( $tag, (string) json_encode( $settings, JSON_UNESCAPED_SLASHES ) );
				$html                            = substr( $html, 0, $tags[ $i ]['start'] ) . $rewrite . substr( $html, $open_end );
			}
		}

		return $html;
	}

	/**
	 * The player Elementor would build for a background link, as the embed
	 * URL the provider registry knows — or null when Elementor would treat
	 * the link as a video file.
	 *
	 * The two tests are Elementor's own (background-video.js, activate()),
	 * in its order, because the question is "will Elementor load a player
	 * API for this", not "is this a tidy URL": it loads YouTube's API for
	 * anything on youtube.com, video id or not.
	 *
	 * @param string $link The background_video_link setting.
	 * @return string|null
	 */
	public static function elementor_player_url( string $link ): ?string {
		if ( '' === $link ) {
			return null;
		}
		if ( false !== strpos( $link, 'vimeo.com' ) ) {
			return 'https://player.vimeo.com/video/' . ( preg_match( '#vimeo\.com/(?:[^?\#]*/)?([0-9]+)#', $link, $m ) ? $m[1] : '0' );
		}
		if ( preg_match( '#^(?:https?://)?(?:www\.)?(?:m\.)?(?:youtu\.be/|youtube\.com)#', $link ) ) {
			$id = ElementorVideoRule::youtube_id( $link );
			return 'https://www.youtube.com/embed/' . ( '' !== $id ? rawurlencode( $id ) : '0' );
		}
		return null;
	}

	/**
	 * Whether this background is one to remove: third-party, not vetoed,
	 * and from a provider the owner has not let through.
	 *
	 * @param string $url       What the owner's markup names (asked of the veto).
	 * @param string $canonical The embed URL the provider registry resolves.
	 * @param array  $ctx       Integration context.
	 * @return bool
	 */
	private function is_gated( string $url, string $canonical, array $ctx ): bool {
		if ( '' === $canonical || HostMatcher::FOREIGN !== $this->hosts->classify( $canonical ) ) {
			return false;
		}
		if ( ! empty( $ctx['force_gate'] ) ) {
			return true;
		}
		if ( null !== $this->should_gate && ! call_user_func( $this->should_gate, true, $url, $ctx ) ) {
			return false;
		}
		$host = $this->hosts->host_of( $canonical );
		if ( null === $host ) {
			return false;
		}
		$provider = $this->providers->resolve_for_url( $canonical, $host );
		return false !== $provider['enabled'];
	}

	/**
	 * The iframe element that starts at (or after whitespace from) $from.
	 *
	 * @param string $html HTML.
	 * @param int    $from Offset.
	 * @return array|null A find_tags() match.
	 */
	private function iframe_at( string $html, int $from ): ?array {
		foreach ( $this->scanner->find_tags( $html, 'iframe' ) as $iframe ) {
			if ( $iframe['start'] >= $from ) {
				return '' === trim( substr( $html, $from, $iframe['start'] - $from ) ) ? $iframe : null;
			}
		}
		return null;
	}

	/**
	 * @param array  $attributes Parsed attributes.
	 * @param string $class_name Class name to look for.
	 * @return bool
	 */
	private static function has_class( array $attributes, string $class_name ): bool {
		if ( ! isset( $attributes['class'] ) || ! is_string( $attributes['class'] ) ) {
			return false;
		}
		$classes = preg_split( '/\s+/', trim( $attributes['class'] ) );
		return is_array( $classes ) && in_array( $class_name, $classes, true );
	}
}
