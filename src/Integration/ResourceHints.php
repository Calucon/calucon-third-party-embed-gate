<?php
/**
 * Resource hints (PLAN.md §9.14): a preconnect to a gated provider contacts
 * them on page load and undermines the entire gate.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

use ConsentGate\Support\ResourceHints as Scrubber;

/**
 * Hooks wp_resource_hints.
 */
final class ResourceHints {

	/** @var Scrubber */
	private Scrubber $scrubber;

	public function __construct( Scrubber $scrubber ) {
		$this->scrubber = $scrubber;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_resource_hints', array( $this, 'filter' ), 10, 2 );
	}

	/**
	 * @param array  $urls     Hint entries.
	 * @param string $relation Hint relation.
	 * @return array
	 */
	public function filter( $urls, $relation ): array {
		return $this->scrubber->filter( (array) $urls, (string) $relation );
	}
}
