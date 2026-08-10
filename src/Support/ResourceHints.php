<?php
/**
 * Resource-hint scrubbing (PLAN.md §9.14). WordPress-free pure logic; the
 * wp_resource_hints filter lives in Integration/.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Support;

use ConsentGate\Detection\HostMatcher;

/**
 * preconnect opens a TCP+TLS connection to the provider on page load — that
 * contacts them and reveals the visitor's IP, so it must be stripped for any
 * host the plugin gates. dns-prefetch resolves a name through the visitor's
 * own resolver — no contact with the provider — but it is pointless once
 * gated and is removed for tidiness.
 */
final class ResourceHints {

	/** @var string[] Normalised provider hosts the plugin gates. */
	private array $gated_hosts;

	/** @var HostMatcher */
	private HostMatcher $matcher;

	/**
	 * @param string[]    $gated_hosts Hosts from provider match tables.
	 * @param HostMatcher $matcher     For host extraction/normalisation.
	 */
	public function __construct( array $gated_hosts, HostMatcher $matcher ) {
		$this->gated_hosts = array_map( 'strtolower', $gated_hosts );
		$this->matcher     = $matcher;
	}

	/**
	 * Filter a wp_resource_hints URL list.
	 *
	 * @param array  $urls     Hint entries: strings or arrays with 'href'.
	 * @param string $relation 'preconnect', 'dns-prefetch', …
	 * @return array
	 */
	public function filter( array $urls, string $relation ): array {
		if ( ! in_array( $relation, array( 'preconnect', 'dns-prefetch' ), true ) ) {
			return $urls;
		}

		$kept = array();
		foreach ( $urls as $entry ) {
			$href = is_array( $entry ) ? ( isset( $entry['href'] ) ? (string) $entry['href'] : '' ) : (string) $entry;
			if ( ! $this->is_gated_host( $href ) ) {
				$kept[] = $entry;
			}
		}

		return $kept;
	}

	/**
	 * @param string $href Hint URL (may be a bare host, as core allows).
	 * @return bool
	 */
	private function is_gated_host( string $href ): bool {
		$href = trim( $href );
		if ( '' === $href ) {
			return false;
		}

		$host = $this->matcher->host_of( $href );
		if ( null === $host ) {
			// Core accepts bare host names as hints.
			$host = strtolower( rtrim( $href, '.' ) );
		}

		return in_array( $host, $this->gated_hosts, true );
	}
}
