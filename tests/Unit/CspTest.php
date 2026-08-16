<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Support\Csp;
use PHPUnit\Framework\TestCase;

final class CspTest extends TestCase {

	public function test_load_host_replaces_match_hosts_in_frame_src(): void {
		$directives = Csp::directives( Descriptors::all() );

		// YouTube rewrites to nocookie: only the load host is needed.
		self::assertContains( 'www.youtube-nocookie.com', $directives['frame-src'] );
		self::assertNotContains( 'www.youtube.com', $directives['frame-src'] );
		// Vimeo keeps its original host (dnt merge, no host rewrite).
		self::assertContains( 'player.vimeo.com', $directives['frame-src'] );
	}

	public function test_script_hosts_land_in_script_src(): void {
		$directives = Csp::directives( Descriptors::all() );

		self::assertContains( 'strava-embeds.com', $directives['script-src'] );
		self::assertContains( 'platform.twitter.com', $directives['script-src'] );
	}

	public function test_disabled_providers_are_excluded(): void {
		$providers = array(
			array(
				'id'      => 'x',
				'enabled' => false,
				'match'   => array( 'iframe_host' => array( 'x.example' ) ),
			),
			array(
				'id'    => 'y',
				'match' => array( 'iframe_host' => array( 'y.example' ) ),
			),
		);

		self::assertSame(
			array( 'frame-src' => array( 'y.example' ), 'script-src' => array() ),
			Csp::directives( $providers )
		);
	}

	public function test_snippet_renders_https_hosts_per_directive(): void {
		$snippet = Csp::snippet(
			array(
				array( 'id' => 'a', 'match' => array( 'iframe_host' => array( 'a.example' ) ) ),
				array( 'id' => 'b', 'match' => array( 'script_host' => array( 'b.example' ) ), 'strategy' => 'script' ),
			)
		);

		self::assertSame(
			"frame-src https://a.example;\nscript-src https://b.example;",
			$snippet
		);
	}
}
