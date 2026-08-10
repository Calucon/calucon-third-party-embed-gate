<?php
/**
 * Resource-hint scrubbing (PLAN.md §9.14). WordPress-free pure logic; the
 * wp_resource_hints filter lives in Integration/.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Support;

use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;

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
	 * Relations that reveal the visitor to the provider (preconnect opens
	 * TCP+TLS; prefetch/prerender/preload fetch the resource outright) plus
	 * dns-prefetch, pointless once gated.
	 */
	private const HINT_RELATIONS = array( 'preconnect', 'dns-prefetch', 'prefetch', 'prerender', 'preload', 'modulepreload' );

	/**
	 * Filter a wp_resource_hints URL list.
	 *
	 * @param array  $urls     Hint entries: strings or arrays with 'href'.
	 * @param string $relation 'preconnect', 'dns-prefetch', 'prefetch', …
	 * @return array
	 */
	public function filter( array $urls, string $relation ): array {
		if ( ! in_array( $relation, self::HINT_RELATIONS, true ) ) {
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
	 * Filter a wp_preload_resources list (WP 6.1+) — a separate filter from
	 * wp_resource_hints, and a full fetch at page load, so a preload of a
	 * provider SDK defeats the gate entirely.
	 *
	 * @param array $resources Entries with 'href'.
	 * @return array
	 */
	public function filter_preload( array $resources ): array {
		$kept = array();
		foreach ( $resources as $resource ) {
			$href = is_array( $resource ) && isset( $resource['href'] ) ? (string) $resource['href'] : '';
			if ( ! $this->is_gated_host( $href ) ) {
				$kept[] = $resource;
			}
		}
		return $kept;
	}

	/**
	 * Remove literal <link> hint tags for gated hosts from a document.
	 * Performance plugins (Perfmatters, Optimization Detective) and themes
	 * print these directly, bypassing every filter (§9.14). Only available
	 * where the whole document is in hand — the output buffer.
	 *
	 * @param string      $html    Document HTML.
	 * @param HtmlScanner $scanner Tag scanner.
	 * @return string
	 */
	public function scrub_tags( string $html, HtmlScanner $scanner ): string {
		if ( false === stripos( $html, '<link' ) ) {
			return $html;
		}

		$matches = $scanner->find_tags( $html, 'link' );
		foreach ( array_reverse( $matches ) as $tag_match ) {
			$attrs = $tag_match['attributes'];
			$rel   = isset( $attrs['rel'] ) && is_string( $attrs['rel'] ) ? strtolower( trim( $attrs['rel'] ) ) : '';
			$href  = isset( $attrs['href'] ) && is_string( $attrs['href'] ) ? $attrs['href'] : '';
			if ( ! in_array( $rel, self::HINT_RELATIONS, true ) || ! $this->is_gated_host( $href ) ) {
				continue;
			}
			$html = substr( $html, 0, $tag_match['start'] ) . substr( $html, $tag_match['end'] );
		}

		return $html;
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

		// Exact match, or related by subdomain in either direction: a hint
		// to youtube.com serves the listed www.youtube.com, and a hint to
		// i.ytimg.com serves the listed ytimg host set. Fail closed — a
		// stripped harmless hint costs milliseconds; a kept harmful one
		// opens a connection before consent (invariant 6's asymmetry).
		foreach ( $this->gated_hosts as $gated ) {
			if ( $host === $gated
				|| substr( $host, -1 * strlen( '.' . $gated ) ) === '.' . $gated
				|| substr( $gated, -1 * strlen( '.' . $host ) ) === '.' . $host ) {
				return true;
			}
		}
		return false;
	}
}
