<?php
/**
 * The <embed>/<object> gating rule.
 *
 * Flash-era YouTube markup, PDF viewers and legacy slide embeds still live in
 * long-running blogs' post content, and both tags fire their third-party
 * request on page load exactly like an iframe. WordPress-free by design
 * (PLAN.md §2.2).
 *
 * @package ConsentGate
 */

namespace ConsentGate\Detection;

use ConsentGate\Providers\LoadUrl;
use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;

/**
 * Replaces cross-origin <embed> and <object> elements with the placeholder.
 * The rebuilt element keeps its original tag: an <object> comes back as an
 * <object>, never as something with more capability (invariant 7).
 */
final class EmbedObjectRule {

	/** @var HtmlScanner */
	private HtmlScanner $scanner;

	/** @var HostMatcher */
	private HostMatcher $hosts;

	/** @var Registry */
	private Registry $providers;

	/** @var PlaceholderRenderer */
	private PlaceholderRenderer $renderer;

	/** @var callable|null Bridge for consent_gate_should_gate: fn( bool, string $url, array $ctx ): bool */
	private $should_gate;

	/** @var callable|null Called once per gated embed: fn( array $provider, array $ctx ): void */
	private $on_gated;

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
	 * Gate every cross-origin <embed>/<object> in a fragment.
	 *
	 * @param string $html Content HTML.
	 * @param array  $ctx  Integration context: post_id, block, integration.
	 * @return string
	 */
	public function apply( string $html, array $ctx = array() ): string {
		// Cheap probe before any parsing (PLAN.md §9.16). '<embed' must not
		// false-positive on '<embed' being absent while '<object' is present,
		// so probe both.
		if ( false === stripos( $html, '<embed' ) && false === stripos( $html, '<object' ) ) {
			return $html;
		}

		$matches = $this->collect( $html );
		if ( array() === $matches ) {
			return $html;
		}

		// Replace back-to-front so earlier offsets stay valid.
		foreach ( array_reverse( $matches ) as $match ) {
			$src = $match['url'];

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
				continue;
			}
			$provider['strategy'] = 'iframe';

			if ( '' === $provider['fallback'] ) {
				$provider['fallback'] = $src;
			}

			$placeholder = $this->renderer->render(
				$provider,
				LoadUrl::for_provider( $provider, $src ),
				$match['attributes'],
				$ctx,
				array( 'tag' => $match['tag'] )
			);

			$html = substr( $html, 0, $match['start'] ) . $placeholder . substr( $html, $match['end'] );

			if ( null !== $this->on_gated ) {
				call_user_func( $this->on_gated, $provider, $ctx );
			}
		}

		return $html;
	}

	/**
	 * Gateable matches in document order, overlap-free.
	 *
	 * The classic Flash pairing nests an <embed> inside an <object> as its
	 * own fallback — that is ONE embed, not two: the outer object's span
	 * consumes the inner tag.
	 *
	 * @param string $html Content HTML.
	 * @return array[] Each: start, end, attributes, tag, url.
	 */
	private function collect( string $html ): array {
		$spans = array();

		foreach ( $this->scanner->find_tags( $html, 'object' ) as $match ) {
			$url = $this->object_url( $html, $match );
			if ( null !== $url ) {
				$spans[] = array(
					'start'      => $match['start'],
					'end'        => $match['end'],
					'attributes' => $match['attributes'],
					'tag'        => 'object',
					'url'        => $url,
				);
			}
		}

		foreach ( $this->scanner->find_tags( $html, 'embed' ) as $match ) {
			$url = $this->foreign_url( $match['attributes'], 'src' );
			if ( null !== $url ) {
				$spans[] = array(
					'start'      => $match['start'],
					'end'        => $match['end'],
					'attributes' => $match['attributes'],
					'tag'        => 'embed',
					'url'        => $url,
				);
			}
		}

		usort(
			$spans,
			static function ( array $a, array $b ): int {
				return $a['start'] - $b['start'];
			}
		);

		$kept     = array();
		$last_end = -1;
		foreach ( $spans as $span ) {
			if ( $span['start'] < $last_end ) {
				continue;
			}
			$kept[]   = $span;
			$last_end = $span['end'];
		}

		return $kept;
	}

	/**
	 * The URL an <object> would request: its data attribute, or the movie/src
	 * <param> inside it (how Flash-era markup spelled the target).
	 *
	 * @param string $html      Full fragment.
	 * @param array  $tag_match Scanner match for the object tag.
	 * @return string|null
	 */
	private function object_url( string $html, array $tag_match ) {
		$url = $this->foreign_url( $tag_match['attributes'], 'data' );
		if ( null !== $url ) {
			return $url;
		}

		$span = substr( $html, $tag_match['start'], $tag_match['end'] - $tag_match['start'] );
		foreach ( $this->scanner->find_tags( $span, 'param' ) as $param ) {
			$attrs = $param['attributes'];
			$name  = isset( $attrs['name'] ) && is_string( $attrs['name'] ) ? strtolower( trim( $attrs['name'] ) ) : '';
			if ( ! in_array( $name, array( 'movie', 'src' ), true ) ) {
				continue;
			}
			if ( isset( $attrs['value'] ) && is_string( $attrs['value'] ) ) {
				$value = trim( $attrs['value'] );
				if ( HostMatcher::FOREIGN === $this->hosts->classify( $value ) ) {
					return $value;
				}
			}
		}

		return null;
	}

	/**
	 * @param array  $attributes Lowercased, decoded attributes.
	 * @param string $name       Attribute carrying the URL.
	 * @return string|null The trimmed URL when it is foreign, else null.
	 */
	private function foreign_url( array $attributes, string $name ) {
		if ( ! isset( $attributes[ $name ] ) || ! is_string( $attributes[ $name ] ) ) {
			return null;
		}
		$url = trim( $attributes[ $name ] );
		if ( HostMatcher::FOREIGN !== $this->hosts->classify( $url ) ) {
			return null;
		}
		return $url;
	}
}
