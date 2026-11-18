<?php
/**
 * LoadUrl: what an embed is loaded from after the click.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\HostMatcher;
use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Providers\LoadUrl;
use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Tests\Support\PipelineFactory;
use PHPUnit\Framework\TestCase;

final class LoadUrlTest extends TestCase {

	private static function load( string $src ): string {
		$host     = ( new HostMatcher( array( 'example.test' ) ) )->host_of( $src );
		$provider = ( new Registry( Descriptors::all() ) )->resolve_for_url( $src, (string) $host );
		return LoadUrl::for_provider( $provider, $src );
	}

	/** @dataProvider youtube */
	public function test_youtube_moves_to_the_cookieless_host_and_keeps_what_the_owner_set( string $src, string $expected ): void {
		self::assertSame( $expected, self::load( $src ) );
	}

	public static function youtube(): array {
		$base = 'https://www.youtube-nocookie.com/embed/';
		return array(
			'plain'                       => array( 'https://www.youtube.com/embed/y_pjE_p1HwE', $base . 'y_pjE_p1HwE' ),
			// What WordPress's own oEmbed adds: an attribution marker.
			'oembed marker dropped'       => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed', $base . 'y_pjE_p1HwE' ),
			'share id dropped'            => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?si=AbCdEfGhIjKlMnOp', $base . 'y_pjE_p1HwE' ),
			// Until 1.0.2 every one of these lost its parameters.
			'start time'                  => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed&start=90', $base . 'y_pjE_p1HwE?start=90' ),
			'a playlist'                  => array( 'https://www.youtube.com/embed/videoseries?list=PLexampleExampleExample0001', $base . 'videoseries?list=PLexampleExampleExample0001' ),
			'a video in a playlist'       => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?list=PLexample0001&index=3', $base . 'y_pjE_p1HwE?list=PLexample0001&index=3' ),
			'player options'              => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?rel=0&cc_load_policy=1&hl=de&controls=0', $base . 'y_pjE_p1HwE?rel=0&cc_load_policy=1&hl=de&controls=0' ),
			'the JS API'                  => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?enablejsapi=1&origin=https%3A%2F%2Fexample.test', $base . 'y_pjE_p1HwE?enablejsapi=1&origin=https%3A%2F%2Fexample.test' ),
			// Invariant 8, in every spelling the query can carry it.
			'autoplay never'              => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?autoplay=1&mute=1&start=5', $base . 'y_pjE_p1HwE?mute=1&start=5' ),
			'autoplay alone'              => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?autoplay=1', $base . 'y_pjE_p1HwE' ),
			'already cookieless'          => array( 'https://www.youtube-nocookie.com/embed/y_pjE_p1HwE?start=5', $base . 'y_pjE_p1HwE?start=5' ),
			'protocol-relative'           => array( '//www.youtube.com/embed/y_pjE_p1HwE?start=5', $base . 'y_pjE_p1HwE?start=5' ),
			'values are re-encoded'       => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?hl=de"><script>', $base . 'y_pjE_p1HwE?hl=de%22%3E%3Cscript%3E' ),
			'a fragment does not ride on' => array( 'https://www.youtube.com/embed/y_pjE_p1HwE?start=5#t=9', $base . 'y_pjE_p1HwE?start=5' ),
		);
	}

	public function test_vimeo_keeps_its_url_and_gains_dnt(): void {
		self::assertSame( 'https://player.vimeo.com/video/76979871?h=abc123&dnt=1', self::load( 'https://player.vimeo.com/video/76979871?h=abc123&autoplay=1' ) );
	}

	public function test_a_gated_playlist_still_names_its_list(): void {
		$out = PipelineFactory::gate( '<iframe width="560" height="315" src="https://www.youtube.com/embed/videoseries?list=PLexampleExampleExample0001&amp;autoplay=1" title="Playlist" allowfullscreen></iframe>' );

		self::assertStringContainsString( '"src":"https://www.youtube-nocookie.com/embed/videoseries?list=PLexampleExampleExample0001"', $out );
		self::assertStringNotContainsString( 'autoplay', $out );
	}
}
