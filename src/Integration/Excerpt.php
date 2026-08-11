<?php
/**
 * Excerpts (PLAN.md §3.3): strip embeds entirely rather than gating — a
 * placeholder in an excerpt is noise.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		if ( ! $this->plugin->has_gateable_markup( $excerpt ) ) {
			return $excerpt;
		}

		// Editing contexts must see the original markup (invariant 4): the
		// excerpt column in list tables and excerpt.rendered for editors are
		// not places to silently delete content. Feeds still strip — a
		// placeholder in RSS is nonsense (§9.3) and the bail covers is_feed.
		if ( ! is_feed() && $this->plugin->should_bail() ) {
			return $excerpt;
		}

		return $this->plugin->strip( $excerpt );
	}
}
