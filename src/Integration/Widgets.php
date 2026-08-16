<?php
/**
 * Widget integrations (PLAN.md §3.3): legacy widget areas still exist
 * everywhere.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Plugin;

/**
 * Hooks widget_block_content (block widgets), widget_text (legacy text
 * widgets and the Custom HTML widget's back-compat pass) and
 * widget_text_content (the Text widget's visual mode).
 */
final class Widgets {

	/** @var Plugin */
	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'widget_block_content', array( $this, 'filter' ), 10 );
		add_filter( 'widget_text', array( $this, 'filter' ), 10 );
		// The Text widget's visual mode runs autoembed (priority 8) and
		// do_shortcode (priority 11) on widget_text_content AFTER widget_text
		// has been applied — a bare YouTube URL or [embed] shortcode expands
		// to an iframe only there. Priority 20 runs after both; re-gating
		// already-gated markup is a no-op (§9.1 probes).
		add_filter( 'widget_text_content', array( $this, 'filter' ), 20 );
	}

	/**
	 * @param string $content Widget HTML.
	 * @return string
	 */
	public function filter( $content ): string {
		$content = (string) $content;

		if ( ! $this->plugin->has_gateable_markup( $content ) || $this->plugin->should_bail() ) {
			return $content;
		}

		return $this->plugin->gate(
			$content,
			array(
				'integration' => 'widget',
				'block'       => null,
				'post_id'     => null,
			)
		);
	}
}
