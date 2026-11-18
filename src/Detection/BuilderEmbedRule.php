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
		return $html;
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
			$close = $this->closing_div( $html, $open_end );
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

			$placeholder = $this->renderer->render( $provider, LoadUrl::for_provider( $provider, $src ), $frame, $ctx );
			$html        = substr( $html, 0, $elements[ $i ]['start'] ) . $placeholder . substr( $html, $elements[ $i ]['end'] );

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
	 * Offset of the </div> that closes the element whose opening tag ended
	 * at $from. Depth is counted over the scanner's tags, so a <div inside
	 * a script template or an attribute value does not count.
	 *
	 * @param string $html HTML.
	 * @param int    $from Offset just after the opening tag.
	 * @return int|null
	 */
	private function closing_div( string $html, int $from ): ?int {
		$opens = array();
		foreach ( $this->scanner->find_tags( $html, 'div' ) as $div ) {
			if ( $div['start'] >= $from ) {
				$opens[] = $div['start'];
			}
		}
		$scripts = array();
		foreach ( $this->scanner->find_tags( $html, 'script' ) as $script ) {
			if ( $script['end'] > $from ) {
				$scripts[] = array( $script['start'], $script['end'] );
			}
		}

		$depth = 1;
		$pos   = $from;
		$next  = 0;
		while ( preg_match( '#</div\s*>#i', $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$at = (int) $m[0][1];
			foreach ( $scripts as $span ) {
				if ( $at >= $span[0] && $at < $span[1] ) {
					$pos = $span[1]; // A closing tag inside script text is text.
					continue 2;
				}
			}
			$count = count( $opens );
			while ( $next < $count && $opens[ $next ] < $at ) {
				++$depth;
				++$next;
			}
			--$depth;
			if ( 0 === $depth ) {
				return $at;
			}
			$pos = $at + strlen( $m[0][0] );
		}
		return null;
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
