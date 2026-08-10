<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Providers\Provider;
use ConsentGate\Rendering\PlaceholderRenderer;
use PHPUnit\Framework\TestCase;

final class PlaceholderRendererTest extends TestCase {

	private function render( array $attributes, string $src = 'https://www.youtube.com/embed/x' ): string {
		$provider = Provider::normalize(
			array(
				'id'       => 'generic',
				'label'    => 'www.youtube.com',
				'note'     => 'Note text.',
				'action'   => 'Load it',
				'fallback' => $src,
			)
		);

		return ( new PlaceholderRenderer() )->render( $provider, $src, $attributes );
	}

	private function payload_of( string $html ): array {
		self::assertSame( 1, preg_match( '/data-cg-payload="([^"]*)"/', $html, $m ) );
		return json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
	}

	public function test_markup_contract(): void {
		$html = $this->render( array( 'title' => 'T' ) );

		self::assertStringContainsString( '<div class="cg-embed" role="group" aria-label="', $html );
		self::assertStringContainsString( 'data-cg-provider="generic"', $html );
		self::assertStringContainsString( '<button type="button" class="cg-embed__button">', $html );
		self::assertStringContainsString( '<p class="cg-embed__fallback"><a href="https://www.youtube.com/embed/x" rel="noopener nofollow">', $html );
	}

	public function test_output_never_contains_a_raw_iframe_substring(): void {
		// PLAN.md §9.1: re-processing protection depends on this.
		$html = $this->render( array( 'title' => '<iframe>', 'width' => '500' ) );

		self::assertStringNotContainsStringIgnoringCase( '<iframe', $html );
	}

	public function test_payload_carries_only_safelisted_attributes(): void {
		$payload = $this->payload_of(
			$this->render(
				array(
					'title'   => 'T',
					'width'   => '500',
					'style'   => 'position:absolute;visibility:hidden',
					'srcdoc'  => '<p>x</p>',
					'onload'  => 'evil()',
					'class'   => 'wp-embedded-content',
					'sandbox' => 'allow-scripts',
				)
			)
		);

		// 'class' is safelisted (identity, no capability — wp-embed.js keys
		// its resize handshake on it); style/srcdoc/on* must never survive.
		self::assertSame( array( 'title', 'width', 'sandbox', 'class' ), array_keys( $payload['attrs'] ) );
		self::assertSame( 'allow-scripts', $payload['attrs']['sandbox'] );
		self::assertSame( 'wp-embedded-content', $payload['attrs']['class'] );
	}

	public function test_autoplay_never_survives_the_rebuild(): void {
		$payload = $this->payload_of(
			$this->render( array( 'allow' => 'accelerometer; autoplay; encrypted-media' ) )
		);

		self::assertSame( 'accelerometer; encrypted-media', $payload['attrs']['allow'] );
	}

	public function test_boolean_allowfullscreen_round_trips(): void {
		$payload = $this->payload_of( $this->render( array( 'allowfullscreen' => true ) ) );

		self::assertTrue( $payload['attrs']['allowfullscreen'] );
	}
}
