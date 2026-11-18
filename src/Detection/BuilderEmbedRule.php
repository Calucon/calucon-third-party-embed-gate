<?php
/**
 * Embeds a page builder or a "facade" component builds in the browser.
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
 * Gates players that are not in the HTML as an iframe or a provider script,
 * because something on the page builds them after it has loaded. Elementor
 * has its own rule (ElementorVideoRule); this one holds the others.
 *
 * Where these come from, and what that is worth. Each shape below is the
 * vendor's own markup, read on the vendor's own demo site on 2026-11-18,
 * and each was then exercised the only way a commercial theme can be
 * without a licence: the demo page was served through this pipeline with
 * the vendor's real scripts and stylesheets loaded from the vendor, and the
 * requests before a click were recorded. That proves the rule against the
 * vendor's front-end code. It does not prove it against every option of an
 * installed copy, and docs/field-validation.md says so.
 *
 * Enfold's video element. The iframe exists only inside a template:
 *
 *   <div class='avia-video avia-video-16-9 … av-lazyload-immediate
 *   av-lazyload-video-embed' data-original_url='…'>
 *     <script type='text/html' class='av-video-tmpl'>
 *       <div class='avia-iframe-wrap'><iframe src="…"></iframe></div>
 *     </script>
 *     <div class='av-click-to-play-overlay'>…</div>
 *   </div>
 *
 * and Enfold's script clicks the overlay itself as soon as the page is
 * ready ("av-lazyload-immediate"), which writes the template into the div.
 * A scanner that reads script contents as text — as it must — never sees
 * that iframe. The rule takes the iframe out of the template, lets the
 * iframe rule gate it like any other, and puts the result where Enfold
 * would have put the player; with the template and the overlay gone, there
 * is nothing left for Enfold's click to act on. Without the "immediate"
 * class Enfold already waits for the visitor's click on its own preview
 * image, and that design is left alone.
 *
 * <lite-youtube> / <lite-vimeo>. The widely used "facade" web components
 * (Avada ships them; so do many performance plugins): a custom element
 * carrying only a video id, which paints the provider's thumbnail —
 * i.ytimg.com, i.vimeocdn.com — as its background the moment it is
 * defined, and builds the iframe on click. The click is theirs; the
 * thumbnail request is not anybody's. The element becomes a panel for the
 * same video.
 *
 * WordPress-free: strings and arrays in, strings out.
 */
final class BuilderEmbedRule {

	/**
	 * Class the rebuilt iframe carries where this rule replaced the element
	 * that used to size the player: gate.css stretches such a frame to the
	 * panel's width at the embed's ratio. Copied through the renderer's
	 * attribute safelist like any other class.
	 */
	public const FLUID = 'cg-embed__frame--fluid';

	/** @var HtmlScanner */
	private $scanner;

	/** @var HostMatcher */
	private $hosts;

	/** @var Registry */
	private $providers;

	/** @var PlaceholderRenderer */
	private $renderer;

	/** @var IframeRule Gates the iframe found inside a builder's template. */
	private $iframe_rule;

	/** @var callable|null Bridge for calucon_embed_gate_should_gate. */
	private $should_gate;

	/** @var callable|null fn( array $provider, array $ctx ): void */
	private $on_gated;

	/**
	 * @param HtmlScanner         $scanner     Tag reader.
	 * @param HostMatcher         $hosts       Host classifier.
	 * @param Registry            $providers   Provider registry.
	 * @param PlaceholderRenderer $renderer    Placeholder renderer.
	 * @param IframeRule          $iframe_rule The iframe rule, for templated iframes.
	 * @param callable|null       $should_gate Owner veto (filter bridge).
	 * @param callable|null       $on_gated    Fired once per gated embed.
	 */
	public function __construct(
		HtmlScanner $scanner,
		HostMatcher $hosts,
		Registry $providers,
		PlaceholderRenderer $renderer,
		IframeRule $iframe_rule,
		?callable $should_gate = null,
		?callable $on_gated = null
	) {
		$this->scanner     = $scanner;
		$this->hosts       = $hosts;
		$this->providers   = $providers;
		$this->renderer    = $renderer;
		$this->iframe_rule = $iframe_rule;
		$this->should_gate = $should_gate;
		$this->on_gated    = $on_gated;
	}

	/**
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function apply( string $html, array $ctx = array() ): string {
		// Cheap probes before any parsing (PLAN.md §9.16).
		if ( false !== strpos( $html, 'av-video-tmpl' ) ) {
			$html = $this->gate_enfold_videos( $html, $ctx );
		}
		if ( false !== stripos( $html, '<lite-youtube' ) ) {
			$html = $this->gate_facades( $html, $ctx, 'lite-youtube' );
		}
		if ( false !== stripos( $html, '<lite-vimeo' ) ) {
			$html = $this->gate_facades( $html, $ctx, 'lite-vimeo' );
		}
		if ( false !== stripos( $html, '<video' ) ) {
			$html = $this->gate_media_element_players( $html, $ctx );
		}
		if ( false !== strpos( $html, 'data-plyr-provider' ) ) {
			$html = $this->gate_plyr_players( $html, $ctx );
		}
		if ( false !== strpos( $html, 'premium-video-box-container' ) ) {
			$html = $this->gate_premium_video_boxes( $html, $ctx );
		}
		return $html;
	}

	/**
	 * A <video> whose source is a YouTube or Vimeo PAGE.
	 *
	 * Not a media file: MediaElement.js — WordPress's own player for the
	 * [video] shortcode, and what SiteOrigin's video widget uses — accepts a
	 * watch-page URL as a source and builds the provider's player for it:
	 *
	 *   <video class="wp-video-shortcode" …><source type="video/youtube"
	 *   src="https://www.youtube.com/watch?v=…"></video>
	 *
	 * The browser asks the provider for that "file" first, and then
	 * MediaElement loads the player API (measured on SiteOrigin's widget:
	 * 20 to 25 requests before a click). The element becomes a panel for
	 * the same video. A <video> with a real file is media and is left alone.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	private function gate_media_element_players( string $html, array $ctx ): string {
		$videos = $this->scanner->find_tags( $html, 'video' );

		for ( $i = count( $videos ) - 1; $i >= 0; $i-- ) {
			$video = $videos[ $i ];
			$page  = '';
			if ( isset( $video['attributes']['src'] ) && is_string( $video['attributes']['src'] ) ) {
				$page = trim( $video['attributes']['src'] );
			}
			$src = self::provider_page_embed( $page );
			if ( null === $src ) {
				$inner = substr( $html, $video['start'], $video['end'] - $video['start'] );
				foreach ( $this->scanner->find_tags( $inner, 'source' ) as $source ) {
					$page = isset( $source['attributes']['src'] ) && is_string( $source['attributes']['src'] ) ? trim( $source['attributes']['src'] ) : '';
					$src  = self::provider_page_embed( $page );
					if ( null !== $src ) {
						break;
					}
				}
			}
			if ( null === $src ) {
				continue;
			}
			$frame = array();
			foreach ( array( 'title', 'width', 'height' ) as $name ) {
				if ( isset( $video['attributes'][ $name ] ) && is_string( $video['attributes'][ $name ] ) && preg_match( '/^[0-9]+$/', trim( $video['attributes'][ $name ] ) ) ) {
					$frame[ $name ] = trim( $video['attributes'][ $name ] );
				}
			}
			$placeholder = $this->panel_for( $src, $page, $frame, $ctx );
			if ( null === $placeholder ) {
				continue;
			}
			$html = substr( $html, 0, $video['start'] ) . $placeholder . substr( $html, $video['end'] );
		}

		return $html;
	}

	/**
	 * Plyr's embed markup: `<div data-plyr-provider="youtube"
	 * data-plyr-embed-id="…">`, which `new Plyr()` turns into the provider's
	 * player (Essential Addons' Sticky Video; any theme using Plyr). The
	 * element stays — scripts address it by id — but the two attributes go,
	 * so Plyr finds a plain div and builds nothing, and the panel becomes
	 * its content.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	private function gate_plyr_players( string $html, array $ctx ): string {
		$divs = $this->scanner->find_tags( $html, 'div' );

		for ( $i = count( $divs ) - 1; $i >= 0; $i-- ) {
			$attrs = $divs[ $i ]['attributes'];
			if ( ! isset( $attrs['data-plyr-provider'], $attrs['data-plyr-embed-id'] )
				|| ! is_string( $attrs['data-plyr-provider'] ) || ! is_string( $attrs['data-plyr-embed-id'] ) ) {
				continue;
			}
			$id = trim( $attrs['data-plyr-embed-id'] );
			switch ( strtolower( trim( $attrs['data-plyr-provider'] ) ) ) {
				case 'youtube':
					// Plyr takes an id or a URL here.
					$video = ElementorVideoRule::youtube_id( $id );
					$src   = 'https://www.youtube.com/embed/' . rawurlencode( '' !== $video ? $video : $id );
					break;
				case 'vimeo':
					$src = 'https://player.vimeo.com/video/' . rawurlencode( preg_match( '#([0-9]+)(?:[/?\#].*)?$#', $id, $m ) ? $m[1] : $id );
					break;
				default:
					continue 2; // Not a provider Plyr embeds.
			}
			if ( '' === $id ) {
				continue;
			}
			$open_end = $this->scanner->start_tag_end( $html, $divs[ $i ]['start'] );
			if ( null === $open_end ) {
				continue;
			}
			$close = $this->scanner->closing_tag_offset( $html, $open_end, 'div' );
			if ( null === $close ) {
				continue;
			}
			$placeholder = $this->panel_for( $src, $src, array(), $ctx );
			if ( null === $placeholder ) {
				continue;
			}
			$tag     = substr( $html, $divs[ $i ]['start'], $open_end - $divs[ $i ]['start'] );
			$rewrite = preg_replace( '#\sdata-plyr-(?:provider|embed-id)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $tag );
			if ( ! is_string( $rewrite ) ) {
				continue;
			}
			$html = substr( $html, 0, $divs[ $i ]['start'] ) . $rewrite . $placeholder . substr( $html, $close );
		}

		return $html;
	}

	/**
	 * Premium Addons' Video Box: `.premium-video-box-container` holding a
	 * `.premium-video-box-video-container[data-src]`. Its script builds the
	 * iframe on page load when the box has no thumbnail or the URL says
	 * autoplay, and otherwise on a click on its own overlay — whose
	 * automatic image is the provider's thumbnail, fetched on page load.
	 *
	 * The whole box becomes the panel. Nothing less stands the script
	 * down: with the box gone it finds no element and does nothing, while a
	 * box left behind without its data-src throws. The owner's own overlay
	 * image, where it is on this site, becomes the poster.
	 *
	 * Left alone: a self-hosted file, and the lightbox variant, which loads
	 * nothing until the visitor clicks the owner's image.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	private function gate_premium_video_boxes( string $html, array $ctx ): string {
		$divs = $this->scanner->find_tags( $html, 'div' );

		for ( $i = count( $divs ) - 1; $i >= 0; $i-- ) {
			$attrs = $divs[ $i ]['attributes'];
			if ( ! in_array( 'premium-video-box-container', self::classes_of( $attrs ), true )
				|| isset( $attrs['data-lightbox'] )
				|| ( isset( $attrs['data-type'] ) && 'self' === $attrs['data-type'] ) ) {
				continue;
			}
			$open_end = $this->scanner->start_tag_end( $html, $divs[ $i ]['start'] );
			if ( null === $open_end ) {
				continue;
			}
			$close = $this->scanner->closing_tag_offset( $html, $open_end, 'div' );
			if ( null === $close || ! preg_match( '#\G</div\s*>#i', $html, $end, 0, $close ) ) {
				continue;
			}
			$inner = substr( $html, $open_end, $close - $open_end );
			$src   = '';
			$own   = array();
			foreach ( $this->scanner->find_tags( $inner, 'div' ) as $child ) {
				$classes = self::classes_of( $child['attributes'] );
				if ( '' === $src && in_array( 'premium-video-box-video-container', $classes, true )
					&& isset( $child['attributes']['data-src'] ) && is_string( $child['attributes']['data-src'] ) ) {
					$src = trim( $child['attributes']['data-src'] );
				}
				if ( in_array( 'premium-video-box-image-container', $classes, true )
					&& isset( $child['attributes']['style'] ) && is_string( $child['attributes']['style'] )
					&& preg_match( '#background-image:\s*url\((["\']?)([^"\')]+)\1\)#i', $child['attributes']['style'], $m )
					&& HostMatcher::OWN === $this->hosts->classify( trim( $m[2] ) ) ) {
					$own = array( 'poster' => trim( $m[2] ) );
				}
			}
			if ( '' === $src ) {
				continue;
			}
			$placeholder = $this->panel_for( $src, $src, array(), $ctx + $own );
			if ( null === $placeholder ) {
				continue;
			}
			$html = substr( $html, 0, $divs[ $i ]['start'] ) . $placeholder . substr( $html, $close + strlen( $end[0] ) );
		}

		return $html;
	}

	/**
	 * The embed URL for a provider's PAGE address, or null when the address
	 * is not one MediaElement would build a provider player for.
	 *
	 * @param string $page A `<video>`/`<source>` src.
	 * @return string|null
	 */
	public static function provider_page_embed( string $page ): ?string {
		if ( '' === $page ) {
			return null;
		}
		if ( preg_match( '#^(?:https?:)?//(?:www\.|m\.)?(?:youtube\.com|youtube-nocookie\.com|youtu\.be)/#i', $page ) ) {
			$id = ElementorVideoRule::youtube_id( $page );
			return '' === $id ? null : 'https://www.youtube.com/embed/' . rawurlencode( $id );
		}
		if ( preg_match( '#^(?:https?:)?//(?:www\.|player\.)?vimeo\.com/(?:[^?\#]*/)?([0-9]+)#i', $page, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1];
		}
		return null;
	}

	/**
	 * A panel for an embed URL, through the same decisions as every other
	 * rule: the owner's veto, the provider's switch, the "always" override.
	 *
	 * @param string $src      Embed URL.
	 * @param string $asked    What the markup names (asked of the veto).
	 * @param array  $frame    Attributes for the rebuilt iframe.
	 * @param array  $ctx      Integration context.
	 * @return string|null Null when this embed is not to be gated.
	 */
	private function panel_for( string $src, string $asked, array $frame, array $ctx ): ?string {
		if ( HostMatcher::FOREIGN !== $this->hosts->classify( $src ) ) {
			return null;
		}
		if ( empty( $ctx['force_gate'] ) && null !== $this->should_gate
			&& ! call_user_func( $this->should_gate, true, $asked, $ctx ) ) {
			return null;
		}
		$host = $this->hosts->host_of( $src );
		if ( null === $host ) {
			return null;
		}
		$provider = $this->providers->resolve_for_url( $src, $host );
		if ( empty( $ctx['force_gate'] ) && false === $provider['enabled'] ) {
			return null; // The owner let this provider through; their call.
		}
		$provider['strategy'] = 'iframe';
		if ( '' === $provider['fallback'] ) {
			$provider['fallback'] = $src;
		}
		// Nothing sizes the loaded player any more: the element that did is
		// the one being replaced. The class asks gate.css to do it — at the
		// ratio of the width and height given, or the provider's.
		$frame['class'] = self::FLUID;
		$placeholder    = $this->renderer->render( $provider, LoadUrl::for_provider( $provider, $src ), $frame, $ctx );
		if ( null !== $this->on_gated ) {
			call_user_func( $this->on_gated, $provider, $ctx );
		}
		return $placeholder;
	}

	/**
	 * Enfold's video element: see the class comment.
	 *
	 * @param string $html Fragment.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	private function gate_enfold_videos( string $html, array $ctx ): string {
		$divs = $this->scanner->find_tags( $html, 'div' );

		// Back to front so earlier offsets stay valid.
		for ( $i = count( $divs ) - 1; $i >= 0; $i-- ) {
			$classes = self::classes_of( $divs[ $i ]['attributes'] );
			if ( ! in_array( 'avia-video', $classes, true ) || ! in_array( 'av-lazyload-immediate', $classes, true ) ) {
				continue;
			}
			$open_end = $this->scanner->start_tag_end( $html, $divs[ $i ]['start'] );
			if ( null === $open_end ) {
				continue;
			}
			// The element's own end. Its children are a <script> (raw text to
			// the scanner, so the divs inside the template do not count) and
			// one overlay div.
			$close = $this->scanner->closing_tag_offset( $html, $open_end, 'div' );
			if ( null === $close ) {
				continue;
			}
			$inner    = substr( $html, $open_end, $close - $open_end );
			$template = $this->enfold_template( $inner );
			if ( null === $template ) {
				continue;
			}
			$frames = $this->scanner->find_tags( $template, 'iframe' );
			if ( array() === $frames ) {
				continue;
			}
			$frame = substr( $template, $frames[0]['start'], $frames[0]['end'] - $frames[0]['start'] );
			$gated = $this->iframe_rule->apply( $frame, $ctx );
			if ( $gated === $frame ) {
				continue; // Own host, vetoed, or a provider the owner let through.
			}
			$html = substr( $html, 0, $open_end ) . $gated . substr( $html, $close );
		}

		return $html;
	}

	/**
	 * The contents of the av-video-tmpl script in an Enfold video element.
	 *
	 * @param string $inner The element's inner HTML.
	 * @return string|null
	 */
	private function enfold_template( string $inner ): ?string {
		foreach ( $this->scanner->find_tags( $inner, 'script' ) as $script ) {
			if ( ! in_array( 'av-video-tmpl', self::classes_of( $script['attributes'] ), true ) ) {
				continue;
			}
			$open_end = $this->scanner->start_tag_end( $inner, $script['start'] );
			if ( null === $open_end ) {
				return null;
			}
			$body = substr( $inner, $open_end, $script['end'] - $open_end );
			$end  = strripos( $body, '</script' );
			return false === $end ? $body : substr( $body, 0, $end );
		}
		return null;
	}

	/**
	 * <lite-youtube videoid="…"> and <lite-vimeo videoid="…">.
	 *
	 * @param string $html     Fragment.
	 * @param array  $ctx      Integration context.
	 * @param string $tag_name 'lite-youtube' or 'lite-vimeo'.
	 * @return string
	 */
	private function gate_facades( string $html, array $ctx, string $tag_name ): string {
		$elements = $this->scanner->find_tags( $html, $tag_name );

		for ( $i = count( $elements ) - 1; $i >= 0; $i-- ) {
			$attrs = $elements[ $i ]['attributes'];
			$src   = self::facade_url( $tag_name, $attrs );
			if ( null === $src ) {
				continue;
			}
			if ( empty( $ctx['force_gate'] ) && null !== $this->should_gate
				&& ! call_user_func( $this->should_gate, true, $src, $ctx ) ) {
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
			if ( '' === $provider['fallback'] ) {
				$provider['fallback'] = $src;
			}

			// What the component would have put on its iframe, as far as it
			// says: a title (its own attribute is "playlabel"), a size.
			$frame = array();
			foreach ( array( 'title', 'width', 'height' ) as $name ) {
				if ( isset( $attrs[ $name ] ) && is_string( $attrs[ $name ] ) && '' !== trim( $attrs[ $name ] ) ) {
					$frame[ $name ] = trim( $attrs[ $name ] );
				}
			}
			if ( ! isset( $frame['title'] ) && isset( $attrs['playlabel'] ) && is_string( $attrs['playlabel'] ) && '' !== trim( $attrs['playlabel'] ) ) {
				$frame['title'] = trim( $attrs['playlabel'] );
			}

			$frame['class'] = self::FLUID; // The custom element sized itself; it is gone.
			$placeholder    = $this->renderer->render( $provider, LoadUrl::for_provider( $provider, $src ), $frame, $ctx );
			$html           = substr( $html, 0, $elements[ $i ]['start'] ) . $placeholder . substr( $html, $elements[ $i ]['end'] );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}

		return $html;
	}

	/**
	 * The embed a facade element stands for, or null when it names none.
	 *
	 * @param string $tag_name   'lite-youtube' or 'lite-vimeo'.
	 * @param array  $attributes The element's attributes.
	 * @return string|null
	 */
	public static function facade_url( string $tag_name, array $attributes ): ?string {
		$id = isset( $attributes['videoid'] ) && is_string( $attributes['videoid'] ) ? trim( $attributes['videoid'] ) : '';
		if ( 'lite-vimeo' === $tag_name ) {
			return '' === $id ? null : 'https://player.vimeo.com/video/' . rawurlencode( $id );
		}
		if ( '' !== $id ) {
			return 'https://www.youtube.com/embed/' . rawurlencode( $id );
		}
		$list = isset( $attributes['playlistid'] ) && is_string( $attributes['playlistid'] ) ? trim( $attributes['playlistid'] ) : '';
		return '' === $list ? null : 'https://www.youtube.com/embed/videoseries?list=' . rawurlencode( $list );
	}

	/**
	 * @param array $attributes Parsed attributes.
	 * @return string[]
	 */
	private static function classes_of( array $attributes ): array {
		if ( ! isset( $attributes['class'] ) || ! is_string( $attributes['class'] ) ) {
			return array();
		}
		$classes = preg_split( '/\s+/', trim( $attributes['class'] ) );
		return is_array( $classes ) ? $classes : array();
	}
}
