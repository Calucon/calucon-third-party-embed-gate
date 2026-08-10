<?php
/**
 * Widget integrations (PLAN.md §3.3): legacy widget areas still exist
 * everywhere.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

use ConsentGate\Plugin;

/**
 * Hooks widget_block_content (block widgets) and widget_text (legacy text
 * widgets).
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
	}

	/**
	 * @param string $content Widget HTML.
	 * @return string
	 */
	public function filter( $content ): string {
		$content = (string) $content;

		if ( ( false === stripos( $content, '<iframe' ) && false === stripos( $content, '<script' ) )
			|| $this->plugin->should_bail() ) {
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
