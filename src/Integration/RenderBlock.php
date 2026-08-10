<?php
/**
 * render_block integration: block themes and Gutenberg content.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

use ConsentGate\Plugin;

/**
 * Hooks render_block. Fires for nested blocks and again for their parent,
 * whose content already contains the rendered children (PLAN.md §9.1) —
 * safe here because placeholders contain no '<iframe' substring, so the
 * probe in IframeRule skips already-gated children.
 */
final class RenderBlock {

	/** @var Plugin */
	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'render_block',
			array( $this, 'filter' ),
			(int) apply_filters( 'consent_gate_render_block_priority', 10 ),
			2
		);
	}

	/**
	 * @param string $content Rendered block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function filter( $content, $block ): string {
		$content = (string) $content;

		if ( ! $this->plugin->has_gateable_markup( $content ) || $this->plugin->should_bail() ) {
			return $content;
		}

		// Per-block override (PLAN.md §7.5), stored as a block attribute by
		// the editor integration: 'never' skips gating for this block (the
		// editor made an explicit call); 'always' forces gating past the
		// should_gate filter and disabled providers.
		$attrs    = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$override = isset( $attrs['consentGate'] ) && is_string( $attrs['consentGate'] ) ? $attrs['consentGate'] : '';
		if ( 'never' === $override ) {
			return $content;
		}

		return $this->plugin->gate(
			$content,
			array(
				'integration' => 'render_block',
				'block'       => isset( $block['blockName'] ) ? $block['blockName'] : null,
				'post_id'     => get_the_ID(),
				'force_gate'  => 'always' === $override,
			)
		);
	}
}
