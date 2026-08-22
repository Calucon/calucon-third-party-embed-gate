<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Providers\Registry;
use PHPUnit\Framework\TestCase;

final class RegistryCapturesTest extends TestCase {

	private function registry(): Registry {
		return new Registry(
			array(
				array(
					'id'       => 'q',
					'label'    => 'Query Player',
					'match'    => array(
						'iframe_host'  => array( 'player.q.example' ),
						'iframe_path'  => '#^/player\.html$#',
						'iframe_query' => '/(?:^|&)video=(?<id>[A-Za-z0-9]+)/',
					),
					'fallback' => 'https://www.q.example/video/{id}',
				),
				array(
					'id'        => 'p',
					'label'     => 'Path Player',
					'match'     => array(
						'iframe_host' => array( 'p.example' ),
						'iframe_path' => '#^/embed/(?<id>[a-z0-9]+)#',
					),
					'load_host' => 'nocookie.p.example',
					'load_path' => '/embed/{id}',
					'fallback'  => 'https://p.example/watch/{id}',
				),
				array(
					'id'       => 'broken',
					'label'    => 'Template without capture',
					'match'    => array( 'iframe_host' => array( 'b.example' ) ),
					'load_host' => 'nocookie.b.example',
					'load_path' => '/x/{id}',
					'fallback' => 'https://b.example/{id}',
				),
			)
		);
	}

	public function test_query_captures_feed_the_fallback(): void {
		$r = $this->registry()->resolve_for_url( 'https://player.q.example/player.html?video=x2abc&mute=1', 'player.q.example' );

		self::assertSame( 'q', $r['id'] );
		self::assertSame( 'https://www.q.example/video/x2abc', $r['fallback'] );
	}

	public function test_query_never_decides_the_match_but_a_missing_capture_drops_the_template(): void {
		$r = $this->registry()->resolve_for_url( 'https://player.q.example/player.html?playlist=zzz', 'player.q.example' );

		self::assertSame( 'q', $r['id'], 'host + path still match' );
		self::assertSame( '', $r['fallback'], 'no literal {id} ever ships; the embed URL becomes the fallback downstream' );
	}

	public function test_path_captures_still_interpolate_both_templates(): void {
		$r = $this->registry()->resolve_for_url( 'https://p.example/embed/abc123?x=1', 'p.example' );

		self::assertSame( '/embed/abc123', $r['load_path'] );
		self::assertSame( 'https://p.example/watch/abc123', $r['fallback'] );
	}

	public function test_uninterpolated_load_path_disables_the_rewrite(): void {
		$r = $this->registry()->resolve_for_url( 'https://b.example/anything', 'b.example' );

		self::assertSame( 'broken', $r['id'] );
		self::assertNull( $r['load_host'], 'no rewrite to a URL with a literal placeholder' );
		self::assertSame( '', $r['load_path'] );
		self::assertSame( '', $r['fallback'] );
	}

	public function test_script_path_captures_feed_the_script_fallback_and_missing_ones_drop_it(): void {
		$registry = new Registry(
			array(
				array(
					'id'       => 'poll',
					'label'    => 'Poll',
					'match'    => array( 'script_host' => array( 'cdn.poll.example' ), 'script_path' => '#^/p/(?P<id>[0-9]+)\\.js$#' ),
					'fallback' => 'https://poll.example/{id}',
					'strategy' => 'script',
				),
			)
		);

		self::assertSame( 'https://poll.example/7451882', $registry->resolve_for_script_url( 'https://cdn.poll.example/p/7451882.js', 'cdn.poll.example' )['fallback'] );
		self::assertSame( '', $registry->resolve_for_script_url( 'https://cdn.poll.example/survey.js', 'cdn.poll.example' )['fallback'] );
	}

	public function test_inline_and_asset_resolution(): void {
		$registry = new Registry(
			array(
				array( 'id' => 'a', 'label' => 'A', 'match' => array( 'iframe_host' => array( 'frames.a.example' ), 'script_host' => array( 'cdn.a.example' ) ) ),
			)
		);

		self::assertSame( 'a', $registry->resolve_for_inline_script( 'var s=document.createElement("script");s.src="https://cdn.a.example/embed.js";' )['id'] );
		self::assertNull( $registry->resolve_for_inline_script( 'var s="https://cdn.a.example.evil/x.js"' ), 'host must be followed by a slash' );
		self::assertNull( $registry->resolve_for_inline_script( 'console.log("hello")' ) );
		self::assertSame( 'a', $registry->resolve_for_asset_host( 'frames.a.example' )['id'] );
		self::assertSame( 'a', $registry->resolve_for_asset_host( 'cdn.a.example' )['id'] );
		self::assertNull( $registry->resolve_for_asset_host( 'fonts.example' ) );
	}
}
