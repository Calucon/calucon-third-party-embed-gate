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

	/** @var callable Returns the Scrubber; resolved lazily so the filtered
	 *                provider set (theme-registered providers included) is
	 *                complete by the time hints are filtered. */
	private $scrubber_source;

	/**
	 * @param callable $scrubber_source fn(): Scrubber.
	 */
	public function __construct( callable $scrubber_source ) {
		$this->scrubber_source = $scrubber_source;
	}

	/**
	 * @return Scrubber
	 */
	private function scrubber(): Scrubber {
		return call_user_func( $this->scrubber_source );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_resource_hints', array( $this, 'filter' ), 10, 2 );
		// A separate filter (WP 6.1+), and a full fetch rather than a mere
		// connection — a preloaded provider SDK defeats the gate entirely.
		add_filter( 'wp_preload_resources', array( $this, 'filter_preload' ) );
	}

	/**
	 * @param array  $urls     Hint entries.
	 * @param string $relation Hint relation.
	 * @return array
	 */
	public function filter( $urls, $relation ): array {
		return $this->scrubber()->filter( (array) $urls, (string) $relation );
	}

	/**
	 * @param array $resources Preload entries.
	 * @return array
	 */
	public function filter_preload( $resources ): array {
		return $this->scrubber()->filter_preload( (array) $resources );
	}
}
