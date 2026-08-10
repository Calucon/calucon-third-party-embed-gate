<?php
/**
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Unit;

use ConsentGate\Detection\HtmlScanner;
use PHPUnit\Framework\TestCase;

final class HtmlScannerTest extends TestCase {

	private HtmlScanner $scanner;

	protected function setUp(): void {
		$this->scanner = new HtmlScanner();
	}

	public function test_minified_markup_with_bare_values_and_newline_after_tag_name(): void {
		// The exact shape from PLAN.md §3.2 that the first production audit missed.
		$html = "<div\nclass=wp-block-embed__wrapper> <iframe\nloading=lazy title=\"Kolkja Cycling\" width=422 height=750 src=\"https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed\" frameborder=0></iframe> </div>";

		$tags = $this->scanner->find_tags( $html, 'iframe' );

		self::assertCount( 1, $tags );
		self::assertSame( 'lazy', $tags[0]['attributes']['loading'] );
		self::assertSame( '422', $tags[0]['attributes']['width'] );
		self::assertSame( 'https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed', $tags[0]['attributes']['src'] );
	}

	public function test_single_quoted_and_attribute_order(): void {
		$tags = $this->scanner->find_tags(
			"<iframe allowfullscreen loading='lazy' src='https://a.example/x'></iframe>",
			'iframe'
		);

		self::assertSame( true, $tags[0]['attributes']['allowfullscreen'] );
		self::assertSame( 'https://a.example/x', $tags[0]['attributes']['src'] );
	}

	public function test_attribute_names_are_case_insensitive_and_lowercased(): void {
		$tags = $this->scanner->find_tags( '<IFRAME SRC="https://a.example/x" Title="T"></IFRAME>', 'iframe' );

		self::assertSame( 'https://a.example/x', $tags[0]['attributes']['src'] );
		self::assertSame( 'T', $tags[0]['attributes']['title'] );
	}

	public function test_entities_are_decoded_in_values(): void {
		$tags = $this->scanner->find_tags(
			'<iframe src="https://a.example/x?a=1&amp;b=2"></iframe>',
			'iframe'
		);

		self::assertSame( 'https://a.example/x?a=1&b=2', $tags[0]['attributes']['src'] );
	}

	public function test_self_closed_iframe(): void {
		$tags = $this->scanner->find_tags( '<iframe src="https://a.example/x" />', 'iframe' );

		self::assertCount( 1, $tags );
		self::assertTrue( $tags[0]['self_closing'] );
		self::assertSame( strlen( '<iframe src="https://a.example/x" />' ), $tags[0]['end'] );
	}

	public function test_span_includes_closing_tag(): void {
		$html = 'a<iframe src="https://a.example/x">fallback</iframe>b';
		$tags = $this->scanner->find_tags( $html, 'iframe' );

		self::assertSame( 1, $tags[0]['start'] );
		self::assertSame( strlen( $html ) - 1, $tags[0]['end'] );
	}

	public function test_no_match_inside_script_style_textarea_pre_or_comment(): void {
		$html = '<script>var x = \'<iframe src="https://a.example/1"></iframe>\';</script>'
			. '<style>/* <iframe src="https://a.example/2"></iframe> */</style>'
			. '<textarea><iframe src="https://a.example/3"></iframe></textarea>'
			. '<pre><iframe src="https://a.example/4"></iframe></pre>'
			. '<!-- <iframe src="https://a.example/5"></iframe> -->';

		self::assertSame( array(), $this->scanner->find_tags( $html, 'iframe' ) );
	}

	public function test_escaped_markup_never_matches(): void {
		self::assertSame(
			array(),
			$this->scanner->find_tags( '&lt;iframe src="https://a.example/x"&gt;&lt;/iframe&gt;', 'iframe' )
		);
	}

	public function test_duplicate_attribute_first_wins(): void {
		$tags = $this->scanner->find_tags(
			'<iframe src="https://first.example/" src="https://second.example/"></iframe>',
			'iframe'
		);

		self::assertSame( 'https://first.example/', $tags[0]['attributes']['src'] );
	}

	public function test_greater_than_inside_quoted_attribute(): void {
		$tags = $this->scanner->find_tags(
			'<iframe title="a > b" src="https://a.example/x"></iframe>',
			'iframe'
		);

		self::assertSame( 'a > b', $tags[0]['attributes']['title'] );
		self::assertSame( 'https://a.example/x', $tags[0]['attributes']['src'] );
	}
}
