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
		// Identity attributes carry no capability but real integrations key
		// on them: the YouTube JS API needs the id, <form target> needs the
		// name, and core's wp-embed.js resize handshake needs the
		// wp-embedded-content class + data-secret (+ legacy security attr).
		'id',
		'name',
		'class',
		'data-secret',
		'security',
	);

	/**
	 * Safelist for rebuilt <embed>/<object> tags — the frame-capability
	 * attributes above don't exist on those elements, and 'type' does.
	 */
	private const ATTRIBUTE_SAFELIST_MEDIA = array(
		'title',
		'width',
		'height',
		'type',
	);

	/**
	 * Safelist for rebuilt <img> tags (§3.5, opt-in image rule). alt is
	 * kept — dropping it would be an accessibility regression on rebuild.
	 */
	private const ATTRIBUTE_SAFELIST_IMG = array(
		'title',
		'width',
		'height',
		'alt',
		'class',
		'loading',
	);

	/** @var callable Translation function; identity outside WordPress. */
	private $translate;

	/** @var callable|null Bridge for consent_gate_placeholder_html. */
	private $filter_html;

	/** @var callable|null Bridge for consent_gate_payload. */
	private $filter_payload;

	/** @var TemplateLoader|null Theme override lookup (PLAN.md §7.4). */
	private ?TemplateLoader $templates;

	/**
	 * @var array Optional bridges for the §7.2 text filters and action:
	 *            'before'   fn( array $provider, array $ctx ): void
	 *            'note'     fn( string $note, array $provider, array $ctx ): string
	 *            'action'   fn( string $action, array $provider, array $ctx ): string
	 *            'fallback' fn( string $url, array $provider, array $ctx ): string
	 */
	private array $bridges;

	/**
	 * @param callable|null       $translate      Maps English strings to the site language.
	 * @param callable|null       $filter_html    fn( string $html, array $provider, array $ctx ): string.
	 * @param callable|null       $filter_payload fn( array $payload, array $provider ): array.
	 * @param TemplateLoader|null $templates      Theme template override lookup.
	 * @param array               $bridges        See the property docblock.
	 */
	public function __construct(
		?callable $translate = null,
		?callable $filter_html = null,
		?callable $filter_payload = null,
		?TemplateLoader $templates = null,
		array $bridges = array()
	) {
		$this->translate      = $translate ?? static function ( string $text ): string {
			return $text;
		};
		$this->filter_html    = $filter_html;
		$this->filter_payload = $filter_payload;
		$this->templates      = $templates;
		$this->bridges        = $bridges;
	}

	/**
	 * Render the placeholder for one gated embed.
	 *
	 * @param array  $provider   Normalised provider descriptor.
	 * @param string $src        URL the front end loads after the click. May
	 *                           be '' for a srcdoc-only embed, whose payload
	 *                           carries the original inline document instead.
	 * @param array  $attributes Original tag attributes (lowercased, decoded).
	 * @param array  $ctx        Integration context (post ID, block name, …).
	 * @param array  $options    Optional: 'tag' => 'embed'|'object' when the
	 *                           rebuilt element is not an iframe.
	 * @return string HTML.
	 */
	public function render( array $provider, string $src, array $attributes, array $ctx = array(), array $options = array() ): string {
		$t = $this->translate;

		if ( isset( $this->bridges['before'] ) ) {
			call_user_func( $this->bridges['before'], $provider, $ctx );
		}
		if ( isset( $this->bridges['note'] ) ) {
			$provider['note'] = (string) call_user_func( $this->bridges['note'], $provider['note'], $provider, $ctx );
		}
		if ( isset( $this->bridges['action'] ) ) {
			$provider['action'] = (string) call_user_func( $this->bridges['action'], $provider['action'], $provider, $ctx );
		}

		$payload = $this->build_payload( $provider, $src, $attributes, $options );

		$label = '' !== $provider['label'] ? $provider['label'] : 'embed';
		/* translators: %s: provider label (usually a host name). */
		$aria_label = sprintf( $t( 'Embedded content from %s' ), $label );
		/* translators: %s: provider label (usually a host name). */
		$fallback_label = sprintf( $t( 'Open on %s' ), $label );
		$fallback_url   = '' !== $provider['fallback'] ? $provider['fallback'] : $src;
		if ( isset( $this->bridges['fallback'] ) ) {
			$fallback_url = (string) call_user_func( $this->bridges['fallback'], $fallback_url, $provider, $ctx );
		}

		// The host that will actually be contacted, carried on the container:
		// activation and consent memory group generic embeds per host, so
		// consenting to one unknown third party never activates another.
		$host = $this->host_of( '' !== $src ? $src : $fallback_url );

		$html = $this->render_via_template( $provider, $payload, $aria_label, $fallback_url, $fallback_label, $ctx, $host );

		if ( '' === $html ) {
			$html = $this->render_builtin( $provider, $payload, $aria_label, $fallback_url, $fallback_label, $host );
		}

		if ( null !== $this->filter_html ) {
			$html = (string) call_user_func( $this->filter_html, $html, $provider, $ctx );
		}

		return $html;
	}

	/**
	 * The built-in §5.1 markup.
	 *
	 * @param array  $provider       Descriptor.
	 * @param array  $payload        Payload array.
	 * @param string $aria_label     Accessible name.
	 * @param string $fallback_url   Fallback link target.
	 * @param string $fallback_label Fallback link text.
	 * @param string $host           Host the embed will contact.
	 * @return string
	 */
	private function render_builtin( array $provider, array $payload, string $aria_label, string $fallback_url, string $fallback_label, string $host = '' ): string {
		$aspect = $this->aspect_of( $provider, $payload );

		return '<div class="cg-embed"'
			. ' role="group"'
			. ' aria-label="' . $this->esc( $aria_label ) . '"'
			. ' data-cg-provider="' . $this->esc( $provider['id'] ) . '"'
			. ( '' !== $host ? ' data-cg-host="' . $this->esc( $host ) . '"' : '' )
			. ( '' !== $aspect ? ' style="--cg-aspect:' . $this->esc( $aspect ) . '"' : '' )
			. ' data-cg-payload="' . $this->esc_json( $payload ) . '">'
			. '<div class="cg-embed__panel">'
			. '<p class="cg-embed__note">' . $this->esc( $provider['note'] ) . '</p>'
			. '<button type="button" class="cg-embed__button">' . $this->esc( $provider['action'] ) . '</button>'
			. '<p class="cg-embed__fallback"><a href="' . $this->esc( $fallback_url ) . '" rel="noopener nofollow">' . $this->esc( $fallback_label ) . '</a></p>'
			. '</div>'
			. '</div>';
	}

	/**
	 * Render through a theme override template when one exists (§7.4).
	 *
	 * @param array  $provider       Descriptor.
	 * @param array  $payload        Payload array.
	 * @param string $aria_label     Accessible name.
	 * @param string $fallback_url   Fallback link target.
	 * @param string $fallback_label Fallback link text.
	 * @param array  $ctx            Integration context.
	 * @param string $host           Host the embed will contact.
	 * @return string '' when no template applies.
	 */
	private function render_via_template( array $provider, array $payload, string $aria_label, string $fallback_url, string $fallback_label, array $ctx, string $host = '' ): string {
		if ( null === $this->templates ) {
			return '';
		}
		$template = $this->templates->placeholder_template();
		if ( '' === $template ) {
			return '';
		}

		return $this->templates->render(
			$template,
			array(
				'provider'       => $provider,
				'ctx'            => $ctx,
				'aria_label'     => $aria_label,
				'note'           => $provider['note'],
				'action'         => $provider['action'],
				'fallback_url'   => $fallback_url,
				'fallback_label' => $fallback_label,
				'payload_attr'   => $this->esc_json( $payload ),
				'aspect'         => $this->aspect_of( $provider, $payload ),
				'host'           => $host,
			)
		);
	}

	/**
	 * @param string $url URL (may be protocol-relative).
	 * @return string Lowercased host, '' when unparseable.
	 */
	private function host_of( string $url ): string {
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		$host = parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	/**
	 * Build the data-cg-payload JSON: only what the front end needs to build
	 * the real node, never the full original tag (PLAN.md §5.2).
	 *
	 * @param array  $provider   Provider descriptor.
	 * @param string $src        Post-consent URL ('' for srcdoc embeds).
	 * @param array  $attributes Original attributes.
	 * @param array  $options    Render options ('tag').
	 * @return array
	 */
	private function build_payload( array $provider, string $src, array $attributes, array $options = array() ): array {
		$attrs = array();

		$tag = isset( $options['tag'] ) && in_array( $options['tag'], array( 'embed', 'object', 'img' ), true )
			? $options['tag']
			: 'iframe';
		if ( 'iframe' === $tag ) {
			$safelist = self::ATTRIBUTE_SAFELIST;
		} elseif ( 'img' === $tag ) {
			$safelist = self::ATTRIBUTE_SAFELIST_IMG;
		} else {
			$safelist = self::ATTRIBUTE_SAFELIST_MEDIA;
		}

		foreach ( $safelist as $name ) {
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

		if ( 'iframe' === $tag && null !== $provider['iframe_allow'] && ! isset( $attrs['allow'] ) ) {
			$attrs['allow'] = $this->strip_autoplay( (string) $provider['iframe_allow'] );
		}

		$payload = array(
			'src'   => $src,
			'attrs' => $attrs ? $attrs : new \stdClass(),
		);

		if ( '' === $src ) {
			// A srcdoc embed has no URL to rebuild from: the payload carries
			// the original inline document verbatim instead, restored only on
			// click — equal privilege, never wider (invariant 7). JSON_HEX_TAG
			// below keeps any markup inside it out of re-processing's way.
			unset( $payload['src'] );
			if ( isset( $attributes['srcdoc'] ) && is_string( $attributes['srcdoc'] ) && '' !== $attributes['srcdoc'] ) {
				$payload['srcdoc'] = $attributes['srcdoc'];
			}
		}

		if ( 'iframe' !== $tag ) {
			$payload['tag'] = $tag;
		}

		if ( 'iframe' !== $provider['strategy'] ) {
			$payload['strategy'] = $provider['strategy'];
		}

		if ( null !== $this->filter_payload ) {
			$payload = (array) call_user_func( $this->filter_payload, $payload, $provider );
		}

		return $payload;
	}

	/**
	 * Aspect hint for layout preservation where no reserved box exists
	 * (PLAN.md §5.3): a gated embed must occupy the space the real one would,
	 * or the page reflows on activation.
	 *
	 * The embed's own width/height wins over the provider's declared aspect:
	 * a 422×750 YouTube short is 9:16 even though the provider default says
	 * 16:9, and the per-embed attributes are the measured truth.
	 *
	 * @param array $provider Descriptor.
	 * @param array $payload  Built payload.
	 * @return string CSS ratio like '16/9', '' when unknown.
	 */
	private function aspect_of( array $provider, array $payload ): string {
		if ( isset( $payload['strategy'] ) && 'iframe' !== $payload['strategy'] ) {
			return ''; // Script embeds size themselves via their companion.
		}

		$attrs = is_array( $payload['attrs'] ) ? $payload['attrs'] : array();
		if ( isset( $attrs['width'], $attrs['height'] )
			&& preg_match( '/^[0-9]+$/', (string) $attrs['width'] )
			&& preg_match( '/^[0-9]+$/', (string) $attrs['height'] )
			&& (int) $attrs['width'] > 0 && (int) $attrs['height'] > 0 ) {
			return (int) $attrs['width'] . '/' . (int) $attrs['height'];
		}

		if ( is_string( $provider['aspect'] ) && preg_match( '/^([0-9]+):([0-9]+)$/', $provider['aspect'], $m ) ) {
			return $m[1] . '/' . $m[2];
		}

		return '';
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
