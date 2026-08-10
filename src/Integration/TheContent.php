<?php
/**
 * the_content integration: classic themes and older content.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

use ConsentGate\Plugin;

/**
 * Hooks the_content at priority 20: after wpautop (10), before typical
 * shortcode-unwrapping plugins (PLAN.md §3.3). Content already gated via
 * render_block contains no '<iframe', so running both hooks is safe.
 */
final class TheContent {

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
			'the_content',
			array( $this, 'filter' ),
			(int) apply_filters( 'consent_gate_the_content_priority', 20 )
		);
	}

	/**
	 * @param string $content Post content HTML.
	 * @return string
	 */
	public function filter( $content ): string {
		$content = (string) $content;

		if ( false === stripos( $content, '<iframe' ) && false === stripos( $content, '<script' ) ) {
			return $content;
		}

		// A placeholder in RSS is nonsense (§9.3): strip the embed; the
		// WordPress fallback blockquote (a plain link) stays in the feed.
		if ( is_feed() ) {
			return $this->plugin->strip( $content );
		}

		if ( $this->plugin->should_bail() ) {
			return $content;
		}

		return $this->plugin->gate(
			$content,
			array(
				'integration' => 'the_content',
				'block'       => null,
				'post_id'     => get_the_ID(),
			)
		);
	}
}
