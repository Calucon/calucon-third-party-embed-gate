<?php
/**
 * Excerpts (PLAN.md §3.3): strip embeds entirely rather than gating — a
 * placeholder in an excerpt is noise.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

use ConsentGate\Plugin;

/**
 * Hooks get_the_excerpt.
 */
final class Excerpt {

	/** @var Plugin */
	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'get_the_excerpt', array( $this, 'filter' ), 10 );
	}

	/**
	 * @param string $excerpt Excerpt HTML.
	 * @return string
	 */
	public function filter( $excerpt ): string {
		$excerpt = (string) $excerpt;

		if ( false === stripos( $excerpt, '<iframe' ) && false === stripos( $excerpt, '<script' ) ) {
			return $excerpt;
		}

		return $this->plugin->strip( $excerpt );
	}
}
