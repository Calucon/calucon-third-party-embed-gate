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
 * WordPress-free: strings and arrays in, strings out.
 */
final class ElementorVideoRule {

	/** The video_type the rewritten settings carry; Elementor ignores it, we recognise it. */
	public const GATED_TYPE = 'calucon-embed-gate';

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
		// Cheap probe before any parsing (PLAN.md §9.16).
		if ( false === strpos( $html, 'elementor-widget-video' ) || false === stripos( $html, 'data-settings' ) ) {
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
			// the one the owner pasted.
			$provider['fallback'] = '' !== $watch ? $watch : $src;

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
	private static function replace_data_settings( string $tag, string $json ): string {
		$encoded = htmlspecialchars( $json, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$out     = preg_replace(
			'#(\sdata-settings\s*=\s*)(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i',
			'${1}"' . str_replace( '$', '\\$', $encoded ) . '"',
			$tag,
			1
		);
		return is_string( $out ) ? $out : $tag;
	}
}
