<?php
/**
 * Server-rendered placeholder panel.
 *
 * WordPress-free by design (PLAN.md §2.2). The markup shape below is the
 * public contract from PLAN.md §5.1 — themes, tests and CMP bridges depend
 * on it. Change it only with a version bump.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Rendering;

/**
 * Renders the §5.1 panel. The panel must work with JavaScript disabled:
 * the fallback link is a real link to a real page (invariant 2), and the
 * whole thing is rendered server-side, never injected by a script.
 */
final class PlaceholderRenderer {

	/**
	 * Attributes copied from the original iframe onto the rebuilt one.
	 * A safelist, never a loop over everything (invariant 7): 'style' would
	 * carry WordPress's position:absolute;visibility:hidden and the visitor
	 * would opt in and watch nothing appear (PLAN.md §5.2); 'srcdoc' and
	 * 'on*' are script vectors.
	 */
	private const ATTRIBUTE_SAFELIST = array(
		'title',
		'width',
		'height',
		'sandbox',
		'loading',
		'allow',
		'allowfullscreen',
		'referrerpolicy',
	);

	/** @var callable Translation function; identity outside WordPress. */
	private $translate;

	/** @var callable|null Bridge for consent_gate_placeholder_html. */
	private $filter_html;

	/** @var callable|null Bridge for consent_gate_payload. */
	private $filter_payload;

	/**
	 * @param callable|null $translate      Maps English strings to the site language.
	 * @param callable|null $filter_html    fn( string $html, array $provider, array $ctx ): string.
	 * @param callable|null $filter_payload fn( array $payload, array $provider ): array.
	 */
	public function __construct( ?callable $translate = null, ?callable $filter_html = null, ?callable $filter_payload = null ) {
		$this->translate = $translate ?: static function ( string $text ): string {
			return $text;
		};
		$this->filter_html    = $filter_html;
		$this->filter_payload = $filter_payload;
	}

	/**
	 * Render the placeholder for one gated embed.
	 *
	 * @param array  $provider   Normalised provider descriptor.
	 * @param string $src        URL the front end loads after the click.
	 * @param array  $attributes Original tag attributes (lowercased, decoded).
	 * @param array  $ctx        Integration context (post ID, block name, …).
	 * @return string HTML.
	 */
	public function render( array $provider, string $src, array $attributes, array $ctx = array() ): string {
		$t       = $this->translate;
		$payload = $this->build_payload( $provider, $src, $attributes );

		$label = '' !== $provider['label'] ? $provider['label'] : 'embed';
		/* translators: %s: provider label (usually a host name). */
		$aria_label     = sprintf( $t( 'Embedded content from %s' ), $label );
		/* translators: %s: provider label (usually a host name). */
		$fallback_label = sprintf( $t( 'Open on %s' ), $label );
		$fallback_url   = '' !== $provider['fallback'] ? $provider['fallback'] : $src;

		$html = '<div class="cg-embed"'
			. ' role="group"'
			. ' aria-label="' . $this->esc( $aria_label ) . '"'
			. ' data-cg-provider="' . $this->esc( $provider['id'] ) . '"'
			. ' data-cg-payload="' . $this->esc_json( $payload ) . '">'
			. '<div class="cg-embed__panel">'
			. '<p class="cg-embed__note">' . $this->esc( $provider['note'] ) . '</p>'
			. '<button type="button" class="cg-embed__button">' . $this->esc( $provider['action'] ) . '</button>'
			. '<p class="cg-embed__fallback"><a href="' . $this->esc( $fallback_url ) . '" rel="noopener nofollow">' . $this->esc( $fallback_label ) . '</a></p>'
			. '</div>'
			. '</div>';

		if ( null !== $this->filter_html ) {
			$html = (string) call_user_func( $this->filter_html, $html, $provider, $ctx );
		}

		return $html;
	}

	/**
	 * Build the data-cg-payload JSON: only what the front end needs to build
	 * the real node, never the full original tag (PLAN.md §5.2).
	 *
	 * @param array  $provider   Provider descriptor.
	 * @param string $src        Post-consent URL.
	 * @param array  $attributes Original attributes.
	 * @return array
	 */
	private function build_payload( array $provider, string $src, array $attributes ): array {
		$attrs = array();

		foreach ( self::ATTRIBUTE_SAFELIST as $name ) {
			if ( ! array_key_exists( $name, $attributes ) ) {
				continue;
			}
			$value = $attributes[ $name ];
			if ( 'allowfullscreen' === $name ) {
				// Present-but-empty is how HTML spells boolean true.
				$attrs[ $name ] = true;
				continue;
			}
			if ( true === $value ) {
				$value = '';
			}
			if ( 'allow' === $name ) {
				$value = $this->strip_autoplay( (string) $value );
				if ( '' === $value ) {
					continue;
				}
			}
			$attrs[ $name ] = (string) $value;
		}

		if ( null !== $provider['iframe_allow'] && ! isset( $attrs['allow'] ) ) {
			$attrs['allow'] = $this->strip_autoplay( (string) $provider['iframe_allow'] );
		}

		$payload = array(
			'src'   => $src,
			'attrs' => $attrs ? $attrs : new \stdClass(),
		);

		if ( 'iframe' !== $provider['strategy'] ) {
			$payload['strategy'] = $provider['strategy'];
		}

		if ( null !== $this->filter_payload ) {
			$payload = (array) call_user_func( $this->filter_payload, $payload, $provider );
		}

		return $payload;
	}

	/**
	 * Audio starting unbidden is a WCAG 1.4.2 failure and not what was asked
	 * for (invariant 8) — the autoplay permission never survives the rebuild.
	 *
	 * @param string $allow Feature-policy allow list.
	 * @return string
	 */
	private function strip_autoplay( string $allow ): string {
		$features = preg_split( '/\s*;\s*/', $allow, -1, PREG_SPLIT_NO_EMPTY );
		$kept     = array();
		foreach ( $features as $feature ) {
			if ( 0 !== stripos( trim( $feature ), 'autoplay' ) ) {
				$kept[] = trim( $feature );
			}
		}
		return implode( '; ', $kept );
	}

	/**
	 * @param string $text Raw text.
	 * @return string Attribute/content-safe HTML.
	 */
	private function esc( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * JSON for an HTML attribute. HEX_TAG guarantees no raw '<iframe'
	 * substring ever appears inside the payload (PLAN.md §9.1).
	 *
	 * @param array $payload Payload.
	 * @return string
	 */
	private function esc_json( array $payload ): string {
		$json = json_encode(
			$payload,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		return htmlspecialchars( (string) $json, ENT_QUOTES, 'UTF-8' );
	}
}
