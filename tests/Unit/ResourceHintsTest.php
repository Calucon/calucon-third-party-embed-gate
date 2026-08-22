<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\HostMatcher;
use CaluconEmbedGate\Support\ResourceHints;
use PHPUnit\Framework\TestCase;

final class ResourceHintsTest extends TestCase {

	private ResourceHints $hints;

	protected function setUp(): void {
		$this->hints = new ResourceHints(
			array( 'www.youtube.com', 'platform.twitter.com' ),
			new HostMatcher( array( 'example.test' ) )
		);
	}

	public function test_preconnect_to_a_gated_provider_is_stripped(): void {
		// preconnect opens a TCP+TLS connection and reveals the visitor's IP.
		self::assertSame(
			array( 'https://cdn.example.test' ),
			$this->hints->filter(
				array( 'https://cdn.example.test', 'https://www.youtube.com' ),
				'preconnect'
			)
		);
	}

	public function test_dns_prefetch_to_a_gated_provider_is_stripped(): void {
		self::assertSame(
			array( '//fonts.example.net' ),
			$this->hints->filter(
				array( '//fonts.example.net', '//www.youtube.com' ),
				'dns-prefetch'
			)
		);
	}

	public function test_bare_host_entries_and_array_entries_are_understood(): void {
		self::assertSame(
			array( array( 'href' => 'https://cdn.example.test', 'crossorigin' => 'anonymous' ) ),
			$this->hints->filter(
				array(
					array( 'href' => 'https://cdn.example.test', 'crossorigin' => 'anonymous' ),
					array( 'href' => 'https://platform.twitter.com' ),
					'www.youtube.com',
				),
				'preconnect'
			)
		);
	}

	public function test_prefetch_and_preload_to_gated_providers_are_stripped(): void {
		// prefetch/preload FETCH the resource at page load — worse than a
		// preconnect, and exactly what performance plugins emit for SDKs.
		$urls = array( 'https://www.youtube.com/embed/x' );

		self::assertSame( array(), $this->hints->filter( $urls, 'prefetch' ) );
		self::assertSame( array(), $this->hints->filter( $urls, 'preload' ) );
		self::assertSame( $urls, $this->hints->filter( $urls, 'stylesheet' ) );
	}

	public function test_sibling_cdn_hosts_are_stripped_via_suffix_matching(): void {
		self::assertSame(
			array(),
			$this->hints->filter( array( 'https://youtube.com' ), 'preconnect' ),
			'parent domain of a listed host'
		);
	}

	public function test_preload_resources_filter_strips_gated_hosts(): void {
		self::assertSame(
			array( array( 'href' => 'https://cdn.example.test/app.js', 'as' => 'script' ) ),
			$this->hints->filter_preload(
				array(
					array( 'href' => 'https://cdn.example.test/app.js', 'as' => 'script' ),
					array( 'href' => 'https://platform.twitter.com/widgets.js', 'as' => 'script' ),
				)
			)
		);
	}

	// ---- scrub_tags(): the literal-<link> path (§9.14, output-buffer mode).
	// Performance plugins print hint tags directly, bypassing every filter —
	// these pin the only defense against that, including its fast-path probe.

	public function test_scrub_tags_removes_gated_hints_and_keeps_everything_else(): void {
		$scanner = new \CaluconEmbedGate\Detection\HtmlScanner();
		$html    = '<link rel="preconnect" href="https://www.youtube.com" crossorigin>'
			. '<link rel="stylesheet" href="https://www.youtube.com/style.css">'
			. '<link rel="preconnect" href="https://cdn.example.test">'
			. '<link rel="dns-prefetch" href="//platform.twitter.com">';

		self::assertSame(
			'<link rel="stylesheet" href="https://www.youtube.com/style.css">'
			. '<link rel="preconnect" href="https://cdn.example.test">',
			$this->hints->scrub_tags( $html, $scanner ),
			'gated hints vanish; a stylesheet (even to a gated host) and own-host hints stay'
		);
	}

	public function test_scrub_tags_handles_minified_unquoted_attributes(): void {
		// The §3.2 trap applies here too: Perfmatters-style output is minified.
		$scanner = new \CaluconEmbedGate\Detection\HtmlScanner();
		$html    = "<link\nrel=preconnect href=//www.youtube.com><p>kept</p>";

		self::assertSame( '<p>kept</p>', $this->hints->scrub_tags( $html, $scanner ) );
	}

	public function test_scrub_tags_probe_is_sound_against_entity_encoded_rel(): void {
		// The fast-path probe looks for literal relation words; the scanner
		// entity-decodes attribute values. An encoded rel must still be
		// scrubbed (invariant 6: never let a tracker through invisibly).
		$scanner = new \CaluconEmbedGate\Detection\HtmlScanner();
		$html    = '<link rel="precon&#110;ect" href="https://www.youtube.com">';

		self::assertSame( '', $this->hints->scrub_tags( $html, $scanner ) );
	}

	public function test_scrub_tags_fast_path_leaves_hintless_documents_untouched(): void {
		$scanner = new \CaluconEmbedGate\Detection\HtmlScanner();
		$html    = '<link rel="stylesheet" href="/app.css"><p>body copy</p>';

		self::assertSame( $html, $this->hints->scrub_tags( $html, $scanner ) );
	}
}
