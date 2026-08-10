<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Detection\HostMatcher;
use ConsentGate\Support\ResourceHints;
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

	public function test_other_relations_pass_through_untouched(): void {
		$urls = array( 'https://www.youtube.com/embed/x' );

		self::assertSame( $urls, $this->hints->filter( $urls, 'prefetch' ) );
		self::assertSame( $urls, $this->hints->filter( $urls, 'preload' ) );
	}
}
