<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Detection\HostMatcher;
use PHPUnit\Framework\TestCase;

final class HostMatcherTest extends TestCase {

	public function test_foreign_host_is_foreign(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://www.youtube.com/embed/x' ) );
	}

	public function test_own_host_and_www_equivalence(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://example.test/player' ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://www.example.test/player' ) );
	}

	public function test_www_equivalence_can_be_disabled(): void {
		$matcher = new HostMatcher( array( 'example.test' ), false );

		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://www.example.test/player' ) );
	}

	public function test_relative_urls_are_own(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( '/frame.html' ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( 'frame.html' ) );
	}

	public function test_non_loading_schemes_are_skipped(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::SKIP, $matcher->classify( '' ) );
		self::assertSame( HostMatcher::SKIP, $matcher->classify( 'about:blank' ) );
		self::assertSame( HostMatcher::SKIP, $matcher->classify( 'data:text/html,hi' ) );
		self::assertSame( HostMatcher::SKIP, $matcher->classify( 'blob:https://a.example/uuid' ) );
		self::assertSame( HostMatcher::SKIP, $matcher->classify( 'javascript:void(0)' ) );
	}

	public function test_protocol_relative_urls_resolve_by_host(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( '//example.test/frame' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( '//player.vimeo.com/video/1' ) );
	}

	public function test_wildcard_own_hosts(): void {
		$matcher = new HostMatcher( array( 'example.test', '*.cdn.example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://eu1.cdn.example.test/frame' ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://cdn.example.test/frame' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://cdn.example.evil/frame' ) );
	}

	public function test_idn_and_punycode_compare_equal(): void {
		$matcher = new HostMatcher( array( 'münchen.example' ) );

		self::assertTrue( $matcher->is_own_host( 'xn--mnchen-3ya.example' ) );
		self::assertTrue( $matcher->is_own_host( 'MÜNCHEN.example.' ) );
	}

	public function test_is_own_filter_can_veto_and_approve(): void {
		$matcher = new HostMatcher(
			array( 'example.test' ),
			true,
			static function ( bool $own, string $host ): bool {
				return 'trusted.example' === $host ? true : $own;
			}
		);

		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://trusted.example/frame' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://other.example/frame' ) );
	}
}
