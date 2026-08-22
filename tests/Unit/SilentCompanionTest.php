<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Providers\Builtin\Descriptors;
use CaluconEmbedGate\Support\Options;
use CaluconEmbedGate\Tests\Support\PipelineFactory;
use PHPUnit\Framework\TestCase;

/**
 * Silent companions (PLAN.md §3.5): loader scripts, inline injectors and
 * stylesheets that belong to a gated panel are gated without a panel of
 * their own and re-added by gate.js after that panel's activation.
 */
final class SilentCompanionTest extends TestCase {

	private function payloads( string $html ): array {
		preg_match_all( '/<(div|span) class="cg-embed( cg-embed--silent)?"[^>]*data-cg-provider="([^"]+)"[^>]*data-cg-payload="([^"]*)"/', $html, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $row ) {
			$out[] = array(
				'silent'   => '' !== $row[2],
				'provider' => $row[3],
				'payload'  => json_decode( html_entity_decode( $row[4], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true ),
			);
		}
		return $out;
	}

	public function test_loader_script_next_to_its_iframe_is_silent_and_carries_the_script_src(): void {
		$html = PipelineFactory::gate(
			"<iframe src='https://video.wordpress.com/embed/kUJmAcSf' width='560' height='315'></iframe><script src='https://v0.wordpress.com/js/next/videopress-iframe.js'></script>"
		);
		$found = $this->payloads( $html );

		self::assertCount( 2, $found );
		self::assertFalse( $found[0]['silent'] );
		self::assertTrue( $found[1]['silent'] );
		self::assertSame( 'videopress', $found[1]['provider'] );
		self::assertSame( 'script', $found[1]['payload']['strategy'] );
		self::assertSame( 'https://v0.wordpress.com/js/next/videopress-iframe.js', $found[1]['payload']['src'] );
		self::assertStringNotContainsString( '<script', $html );
		self::assertSame( 1, substr_count( $html, 'role="group"' ), 'one accessible panel, not two' );
	}

	public function test_lone_loader_script_keeps_its_own_panel(): void {
		$html  = PipelineFactory::gate( "<script src='https://v0.wordpress.com/js/next/videopress-iframe.js'></script>" );
		$found = $this->payloads( $html );

		self::assertCount( 1, $found );
		self::assertFalse( $found[0]['silent'], 'without a panel to attach to, the script is the embed' );
		self::assertStringContainsString( 'cg-embed__fallback', $html );
	}

	public function test_inline_injector_is_gated_as_inline_code_without_a_src(): void {
		$input = '<iframe src="https://www.scribd.com/embeds/110799637/content" width="100%" height="500"></iframe>'
			. '<script>(function(){var s=document.createElement("script");s.src="https://www.scribd.com/javascripts/embed_code/inject.js";document.head.appendChild(s);})()</script>'
			. '<script>var unrelated = 1;</script>';
		$html  = PipelineFactory::gate( $input );
		$found = $this->payloads( $html );

		self::assertCount( 2, $found );
		self::assertTrue( $found[1]['silent'] );
		self::assertArrayNotHasKey( 'src', $found[1]['payload'], 'nothing to load by URL — the code itself is the payload' );
		self::assertStringContainsString( 'embed_code/inject.js', $found[1]['payload']['inline'] );
		self::assertStringContainsString( '<script>var unrelated = 1;</script>', $html, 'inline scripts that mention no provider are untouched' );
		self::assertStringNotContainsString( 'inject.js";document', $html, 'the injector no longer runs on load' );
		self::assertSame( $html, PipelineFactory::gate( $html ), 'idempotent' );
	}

	public function test_inline_loader_that_is_the_embed_gets_a_panel_with_the_companion_link(): void {
		$html  = (string) file_get_contents( dirname( __DIR__ ) . '/Fixtures/crowdsignal-survey-script/expected.html' );
		$found = $this->payloads( $html );

		self::assertCount( 1, $found );
		self::assertFalse( $found[0]['silent'] );
		self::assertStringContainsString( 'survey.js', $found[0]['payload']['inline'] );
		self::assertStringContainsString( 'href="https://7iger.survey.fm/test-embed"', $html );
	}

	public function test_provider_stylesheets_become_link_companions_only_next_to_a_panel(): void {
		$with = PipelineFactory::gate(
			'<link rel="stylesheet" href="https://www.wolframcloud.com/dist/a.css" />'
			. '<script src="https://www.wolframcloud.com/obj/redirect/notebook-embedder-oembed-lib"></script>'
		);
		$found = $this->payloads( $with );
		self::assertCount( 2, $found );
		self::assertTrue( $found[0]['silent'] );
		self::assertSame( 'link', $found[0]['payload']['tag'] );
		self::assertSame( 'https://www.wolframcloud.com/dist/a.css', $found[0]['payload']['src'] );
		self::assertStringNotContainsString( '<link', $with );

		$alone = '<link rel="stylesheet" href="https://www.wolframcloud.com/dist/a.css" />';
		self::assertSame( $alone, PipelineFactory::gate( $alone ), 'a stylesheet alone is not an embed' );
		$unknown = '<link rel="stylesheet" href="https://fonts.example/x.css" /><script src="https://cdn.widget.example/w.js"></script>';
		self::assertStringContainsString( '<link rel="stylesheet" href="https://fonts.example/x.css" />', PipelineFactory::gate( $unknown ), 'unknown hosts are never touched' );
	}

	public function test_disabled_provider_leaves_companions_alone(): void {
		$providers = Options::apply_provider_overrides( Descriptors::all(), array( 'providers' => array( 'scribd' => array( 'enabled' => false ) ) ) );
		$input     = '<iframe src="https://www.scribd.com/embeds/1/content"></iframe><script>var s="https://www.scribd.com/javascripts/embed_code/inject.js";</script>';

		self::assertSame( $input, PipelineFactory::gate( $input, array( 'example.test' ), array(), $providers ) );
	}
}
