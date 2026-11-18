<?php
/**
 * Elementor's video widget: a YouTube player that exists in the HTML only as
 * a JSON attribute.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Providers\LoadUrl;
use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Rendering\PlaceholderRenderer;

/**
 * Gates Elementor's video widget when its player is built client-side.
 *
 * What the server renders for a YouTube video widget is
 *
 *   <div class="… elementor-widget-video" data-settings='{"video_type":
 *   "youtube","youtube_url":"https://www.youtube.com/watch?v=…"}'>
 *     <div class="elementor-wrapper elementor-open-inline">
 *       <div class="elementor-video"></div>
 *     </div>
 *   </div>
 *
 * and nothing else: no iframe, no script naming YouTube. Elementor's own
 * front-end script reads data-settings, loads youtube.com/iframe_api and
 * builds the player — the page contacts YouTube, its image CDN and
 * DoubleClick before any click (measured by the field suite, 2026-08-28,
 * Elementor 4.2.3). No rule that looks for iframes or script tags can see
 * a player that is not in the HTML. This one reads the same JSON Elementor
 * does.
 *
 * Two moves, both required. The wrapper's contents become the placeholder
 * — the panel sits in the box Elementor's CSS already sizes with the
 * widget's aspect ratio — and data-settings is rewritten so Elementor's
 * handler stands down: it bails out unless video_type is 'youtube' (its
 * only API integration), so the rewritten value never reaches
 * getVideoIDFromURL() and no error is thrown either. A second pass sees
 * the rewritten type and leaves the widget alone (idempotent).
 *
 * Scope, deliberately narrow: the YouTube type is the only one Elementor
 * builds from script — Vimeo and Dailymotion render a real <iframe> that
 * IframeRule gates as it gates any other. Lightbox mode ("elementor-open-
 * lightbox") loads nothing before the visitor clicks the owner's overlay
 * and is left to the owner's design. An image overlay from the owner's
 * media library becomes the panel's poster (§5.4), because it is the
 * site's own image; a foreign one is dropped.
 *
 * The atomic element (Elementor 4's "Editor V4", on by default for sites
 * first installed at 4.0 or later) is the same problem in a new shape:
 *
 *   <div data-e-type="e-youtube" class="e-youtube-base …" data-settings=
 *   "{&quot;source&quot;:&quot;https://www.youtube.com/watch?v=…&quot;,…}">
 *   </div>
 *
 * An empty div — no widget wrapper, no elementor-widget-video class — and
 * its own handler script (youtube-handler.js) that loads youtube.com/
 * iframe_api and builds the player: 19 third-party requests before any
 * click (measured 2026-11-17, Elementor 4.4.1), with or without whole-page
 * gating. The panel becomes the div's content, and `source` is emptied —
 * emptied, never removed: the handler calls source.match(), and a missing
 * key throws inside Elementor's own script. With an empty source it finds
 * no video id and returns before it loads anything.
 *
 * WordPress-free: strings and arrays in, strings out.
 */
final class ElementorVideoRule {

	/** The video_type the rewritten settings carry; Elementor ignores it, we recognise it. */
	public const GATED_TYPE = 'calucon-embed-gate';

	/** The data-e-type of Elementor's atomic YouTube element. */
	public const ATOMIC_TYPE = 'e-youtube';

	/** @var HtmlScanner */
	private $scanner;

	/** @var HostMatcher */
	private $hosts;

	/** @var Registry */
	private $providers;

	/** @var PlaceholderRenderer */
	private $renderer;

	/** @var callable|null Bridge for calucon_embed_gate_should_gate. */
	private $should_gate;

	/** @var callable|null fn( array $provider, array $ctx ): void */
	private $on_gated;

	/**
	 * @param HtmlScanner         $scanner     Tag reader.
	 * @param HostMatcher         $hosts       Host classifier.
	 * @param Registry            $providers   Provider registry.
	 * @param PlaceholderRenderer $renderer    Placeholder renderer.
	 * @param callable|null       $should_gate Owner veto (filter bridge).
	 * @param callable|null       $on_gated    Fired once per gated widget.
	 */
	public function __construct(
		HtmlScanner $scanner,
		HostMatcher $hosts,
		Registry $providers,
		PlaceholderRenderer $renderer,
		?callable $should_gate = null,
		?callable $on_gated = null
	) {
		$this->scanner     = $scanner;
		$this->hosts       = $hosts;
		$this->providers   = $providers;
		$this->renderer    = $renderer;
		$this->should_gate = $should_gate;
		$this->on_gated    = $on_gated;
	}

	/**
	 * Gate every Elementor YouTube video widget in the fragment.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function apply( string $html, array $ctx = array() ): string {
		// Cheap probes before any parsing (PLAN.md §9.16).
		if ( false === stripos( $html, 'data-settings' ) ) {
			return $html;
		}
		if ( false !== strpos( $html, self::ATOMIC_TYPE ) ) {
			$html = $this->apply_atomic( $html, $ctx );
		}
		if ( false === strpos( $html, 'elementor-widget-video' ) ) {
			return $html;
		}

		$divs = $this->scanner->find_tags( $html, 'div' );
		if ( array() === $divs ) {
			return $html;
		}

		// Widgets, back to front so earlier offsets stay valid.
		for ( $i = count( $divs ) - 1; $i >= 0; $i-- ) {
			$widget = $divs[ $i ];
			$attrs  = $widget['attributes'];
			if ( ! self::has_class( $attrs, 'elementor-widget-video' ) || ! isset( $attrs['data-settings'] ) || ! is_string( $attrs['data-settings'] ) ) {
				continue;
			}
			$settings = json_decode( $attrs['data-settings'], true );
			if ( ! is_array( $settings ) || ! isset( $settings['video_type'] ) || 'youtube' !== $settings['video_type'] ) {
				continue; // Not YouTube, or already rewritten by an earlier pass.
			}
			$watch = isset( $settings['youtube_url'] ) && is_string( $settings['youtube_url'] ) ? trim( $settings['youtube_url'] ) : '';
			$id    = self::youtube_id( $watch );
			if ( '' === $id ) {
				continue;
			}
			$src = 'https://www.youtube.com/embed/' . rawurlencode( $id );

			if ( empty( $ctx['force_gate'] ) && null !== $this->should_gate
				&& ! call_user_func( $this->should_gate, true, $src, $ctx ) ) {
				continue;
			}

			// The wrapper: the first elementor-wrapper div after the widget's
			// opening tag. Lightbox mode loads nothing before a click on the
			// owner's overlay and keeps the owner's design.
			$wrapper = self::wrapper_after( $divs, $i );
			if ( null === $wrapper || self::has_class( $wrapper['attributes'], 'elementor-open-lightbox' ) ) {
				continue;
			}
			// find_tags() reports element ends (up to the first closing tag,
			// nesting-blind); the contents to replace start where the
			// wrapper's OPENING tag stops and end at its own </div>.
			$wrapper_open_end = $this->scanner->start_tag_end( $html, $wrapper['start'] );
			$widget_open_end  = $this->scanner->start_tag_end( $html, $widget['start'] );
			if ( null === $wrapper_open_end || null === $widget_open_end ) {
				continue;
			}
			$close = self::closing_div( $html, $wrapper_open_end );
			if ( null === $close ) {
				continue;
			}

			$host = $this->hosts->host_of( $src );
			if ( null === $host ) {
				continue;
			}
			$provider = $this->providers->resolve_for_url( $src, $host );
			if ( empty( $ctx['force_gate'] ) && false === $provider['enabled'] ) {
				continue; // The owner let this provider through; their call.
			}
			$provider['strategy'] = 'iframe';
			// The watch page is the honest no-JS link (§9.5): a real page, and
			// the one the owner pasted — but only when it IS a page. The id
			// above is read from anywhere in the string, so a setting like
			// `java\tscript:alert(1)//youtu.be/{id}` still yields an id and a
			// gated player; the link must then fall back to the embed URL,
			// never carry the setting through to an href.
			$provider['fallback'] = HostMatcher::FOREIGN === $this->hosts->classify( $watch ) ? $watch : $src;

			$poster_ctx = $this->poster_from_overlay( substr( $html, $wrapper_open_end, $close - $wrapper_open_end ) );

			$placeholder = $this->renderer->render(
				$provider,
				LoadUrl::for_provider( $provider, $src ),
				array(),
				$ctx + $poster_ctx
			);

			// Inner content of the wrapper → the panel; the wrapper element
			// itself stays, with Elementor's aspect-ratio box around it.
			$html = substr( $html, 0, $wrapper_open_end ) . $placeholder . substr( $html, $close );

			// Then the settings, so Elementor's handler stands down.
			$settings['video_type'] = self::GATED_TYPE;
			unset( $settings['youtube_url'] );
			$tag     = substr( $html, $widget['start'], $widget_open_end - $widget['start'] );
			$rewrite = self::replace_data_settings( $tag, (string) json_encode( $settings, JSON_UNESCAPED_SLASHES ) );
			$html    = substr( $html, 0, $widget['start'] ) . $rewrite . substr( $html, $widget_open_end );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}

		return $html;
	}

	/**
	 * Gate every atomic YouTube element (data-e-type="e-youtube") in the
	 * fragment. See the class comment for the markup and the stand-down.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	private function apply_atomic( string $html, array $ctx ): string {
		$divs = $this->scanner->find_tags( $html, 'div' );

		// Back to front so earlier offsets stay valid.
		for ( $i = count( $divs ) - 1; $i >= 0; $i-- ) {
			$element = $divs[ $i ];
			$attrs   = $element['attributes'];
			if ( ! isset( $attrs['data-e-type'], $attrs['data-settings'] )
				|| ! is_string( $attrs['data-e-type'] ) || ! is_string( $attrs['data-settings'] )
				|| self::ATOMIC_TYPE !== strtolower( trim( $attrs['data-e-type'] ) ) ) {
				continue;
			}
			$settings = json_decode( $attrs['data-settings'], true );
			if ( ! is_array( $settings ) || ! isset( $settings['source'] ) || ! is_string( $settings['source'] ) ) {
				continue; // Nothing Elementor's handler can build a player from.
			}
			$watch = trim( $settings['source'] );
			$id    = self::atomic_youtube_id( $watch );
			if ( '' === $id ) {
				continue; // Emptied by an earlier pass, or not a video for Elementor either.
			}
			$src = 'https://www.youtube.com/embed/' . rawurlencode( $id );

			if ( empty( $ctx['force_gate'] ) && null !== $this->should_gate
				&& ! call_user_func( $this->should_gate, true, $src, $ctx ) ) {
				continue;
			}

			$open_end = $this->scanner->start_tag_end( $html, $element['start'] );
			if ( null === $open_end ) {
				continue;
			}
			$close = self::closing_div( $html, $open_end );
			if ( null === $close ) {
				continue;
			}

			$host = $this->hosts->host_of( $src );
			if ( null === $host ) {
				continue;
			}
			$provider = $this->providers->resolve_for_url( $src, $host );
			if ( empty( $ctx['force_gate'] ) && false === $provider['enabled'] ) {
				continue; // The owner let this provider through; their call.
			}
			$provider['strategy'] = 'iframe';
			// Same rule as the classic widget: the owner's URL is the no-JS
			// link only when it IS a page on someone else's host.
			$provider['fallback'] = HostMatcher::FOREIGN === $this->hosts->classify( $watch ) ? $watch : $src;

			$placeholder = $this->renderer->render(
				$provider,
				LoadUrl::for_provider( $provider, $src ),
				array(),
				$ctx
			);

			// The element's contents (empty as Elementor renders it) → the panel.
			$html = substr( $html, 0, $open_end ) . $placeholder . substr( $html, $close );

			// Then the settings, so Elementor's handler stands down.
			$settings['source'] = '';
			$tag                = substr( $html, $element['start'], $open_end - $element['start'] );
			$rewrite            = self::replace_data_settings( $tag, (string) json_encode( $settings, JSON_UNESCAPED_SLASHES ) );
			$html               = substr( $html, 0, $element['start'] ) . $rewrite . substr( $html, $open_end );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}

		return $html;
	}

	/**
	 * The video id Elementor's atomic handler would play, or '' when it
	 * would play nothing.
	 *
	 * The handler has its own URL pattern (youtube-handler.js,
	 * getYoutubeVideoIdFromUrl), and the question that matters is not "is
	 * this a tidy YouTube URL" but "will Elementor contact YouTube for it".
	 * So the id is ours where ours finds one, and otherwise whatever their
	 * pattern captures — a `source` only they understand must still be
	 * gated, because they load the player API for it all the same.
	 *
	 * @param string $url The element's `source` setting.
	 * @return string
	 */
	public static function atomic_youtube_id( string $url ): string {
		$id = self::youtube_id( $url );
		if ( '' !== $id ) {
			return $id;
		}
		if ( preg_match( '#^(?:https?://)?(?:www\.)?(?:m\.)?(?:youtu\.be/|youtube\.com/(?:(?:watch)?\?(?:.*&)?vi?=|(?:embed|v|vi|user|shorts)/))([^?&"\'>]+)#', $url, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * The YouTube video id out of any URL shape an owner pastes into the
	 * widget: watch?v=, youtu.be/, /embed/, /shorts/, /live/.
	 *
	 * @param string $url URL.
	 * @return string Empty when none.
	 */
	public static function youtube_id( string $url ): string {
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '#[?&]v=([A-Za-z0-9_-]{6,20})#', $url, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#youtu\.be/([A-Za-z0-9_-]{6,20})#i', $url, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{6,20})#i', $url, $m ) ) {
			return $m[1];
		}
		return '';
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

	/**
	 * The elementor-wrapper div that belongs to widget $i: the first one
	 * after it and before the next widget.
	 *
	 * @param array[] $divs All divs in document order.
	 * @param int     $i    Index of the widget div.
	 * @return array|null
	 */
	private static function wrapper_after( array $divs, int $i ): ?array {
		$count = count( $divs );
		for ( $j = $i + 1; $j < $count; $j++ ) {
			$a = $divs[ $j ]['attributes'];
			if ( self::has_class( $a, 'elementor-widget' ) ) {
				return null; // The next widget: this one had no wrapper.
			}
			if ( self::has_class( $a, 'elementor-wrapper' ) ) {
				return $divs[ $j ];
			}
		}
		return null;
	}

	/**
	 * Offset of the </div> that closes the element whose opening tag ended at
	 * $from, by depth counting; null when the markup never closes it.
	 *
	 * @param string $html HTML.
	 * @param int    $from Offset just after the opening tag.
	 * @return int|null
	 */
	private static function closing_div( string $html, int $from ): ?int {
		$depth = 1;
		$pos   = $from;
		while ( preg_match( '#<(/?)div\b#i', $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$at  = (int) $m[0][1];
			$pos = $at + strlen( $m[0][0] );
			if ( '/' === $m[1][0] ) {
				--$depth;
				if ( 0 === $depth ) {
					return $at;
				}
			} else {
				++$depth;
			}
		}
		return null;
	}

	/**
	 * An image overlay the owner chose from their media library becomes the
	 * panel's poster; anything foreign is dropped (a poster is an own-host
	 * image by the §5.4 contract).
	 *
	 * @param string $inner The wrapper's inner HTML.
	 * @return array Either array() or array( 'poster' => url ).
	 */
	private function poster_from_overlay( string $inner ): array {
		if ( ! preg_match( '#elementor-custom-embed-image-overlay[^>]*background-image:\s*url\((["\']?)([^"\')]+)\1\)#i', $inner, $m ) ) {
			return array();
		}
		$url = html_entity_decode( trim( $m[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( HostMatcher::OWN !== $this->hosts->classify( $url ) ) {
			return array();
		}
		return array( 'poster' => $url );
	}

	/**
	 * Replace the data-settings attribute's value inside one opening tag,
	 * whichever way it was quoted (minified HTML may leave it unquoted).
	 *
	 * @param string $tag  The opening tag.
	 * @param string $json New settings JSON.
	 * @return string
	 */
	public static function replace_data_settings( string $tag, string $json ): string {
		$encoded = htmlspecialchars( $json, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Both characters PCRE reads in a replacement string: '$' (a group
		// reference) and '\' (an escape — a JSON "a\\z" lost one backslash and
		// came out as invalid JSON, so Elementor could no longer read its own
		// surviving settings).
		$out = preg_replace(
			'#(\sdata-settings\s*=\s*)(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i',
			'${1}"' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $encoded ) . '"',
			$tag,
			1
		);
		return is_string( $out ) ? $out : $tag;
	}
}
