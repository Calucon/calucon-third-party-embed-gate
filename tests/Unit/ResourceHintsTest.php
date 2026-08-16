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
}
