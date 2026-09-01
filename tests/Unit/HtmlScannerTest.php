<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\HtmlScanner;
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

	/**
	 * Unterminated parsed containers must neither hide embeds nor blow up:
	 * browsers still render markup inside an unclosed <pre>/<code>, so the
	 * iframe after thousands of them must be found — and in linear time (the
	 * failed close-tag search is memoized; without the memo this input class
	 * was quadratic, ~28 ms at 6000 openers and ~1 s extrapolated at 500 KB).
	 */
	public function test_unterminated_parsed_containers_stay_linear_and_visible(): void {
		$scanner = new HtmlScanner();
		// 40 000 openers is the point where the two cost regimes separate by
		// ~90x: linear is ~14 ms, the pre-memo quadratic search extrapolates
		// to ~1.2 s (28 ms measured at 6000, growing as N^2). The 0.3 s bound
		// sits an order of magnitude above linear and well below quadratic, so
		// runner noise cannot flip it either way — a smaller input would let
		// the quadratic regression pass and defeat the test's purpose.
		$html = str_repeat( '<code x>text ', 40000 )
			. '<iframe src="https://www.youtube.com/embed/x"></iframe>';

		$start = microtime( true );
		$tags  = $scanner->find_tags( $html, 'iframe' );
		$spent = microtime( true ) - $start;

		self::assertCount( 1, $tags, 'the iframe after unterminated containers must be found' );
		self::assertLessThan( 0.3, $spent, 'pathological input must stay far from quadratic cost' );
	}

	/**
	 * The memo must not weaken real exclusion: content inside a properly
	 * closed <pre> stays excluded even after an earlier unterminated <code>
	 * poisoned that tag's close-tag search.
	 */
	public function test_memo_does_not_bleed_between_container_tags(): void {
		$scanner = new HtmlScanner();
		$html    = '<code x>unterminated '
			. '<pre><iframe src="https://a.example/hidden"></iframe></pre>'
			. '<iframe src="https://a.example/visible"></iframe>';

		$tags = $scanner->find_tags( $html, 'iframe' );

		self::assertCount( 1, $tags );
		self::assertSame( 'https://a.example/visible', $tags[0]['attributes']['src'] );
	}

	/**
	 * A tag is opened only where a browser opens one. Everything inside a
	 * start tag's attribute values is data — a comment opener, a raw-text
	 * container name, an iframe — and kses keeps '<' inside attribute values,
	 * so any author can write these. The byte-level scan this replaced saw
	 * an opener there and hid every embed after it (silently), or spliced a
	 * placeholder into the attribute.
	 */
	public function test_openers_inside_attribute_values_open_nothing(): void {
		$after = '<iframe src="https://a.example/after"></iframe>';
		foreach ( array(
			'comment in double quotes' => '<div data-x="<!--">x</div>',
			'comment in single quotes' => "<div data-x='<!--'>x</div>",
			'comment bare'             => '<div data-x=<!-->x</div>',
			'textarea'                 => '<a title="<textarea>">x</a>',
			'title'                    => '<span data-t="<title>">x</span>',
			'script'                   => "<em data-s='<script>'>x</em>",
			'style'                    => '<b data-s="<style>">x</b>',
			'pre'                      => '<i data-p="<pre>">x</i>',
		) as $label => $before ) {
			$tags = $this->scanner->find_tags( $before . $after, 'iframe' );
			self::assertCount( 1, $tags, $label );
			self::assertSame( 'https://a.example/after', $tags[0]['attributes']['src'], $label );
		}
	}

	public function test_an_iframe_inside_an_attribute_value_is_not_a_tag(): void {
		self::assertSame( array(), $this->scanner->find_tags( '<img alt="<iframe src=https://a.example/x>" src="/y.png">', 'iframe' ) );
		self::assertSame( array(), $this->scanner->find_tags( "<div title='<iframe src=\"https://a.example/x\"></iframe>'>q</div>", 'iframe' ) );
	}

	public function test_an_unterminated_start_tag_swallows_the_rest_as_a_browser_does(): void {
		// The quote never closes: to a browser everything after it is the
		// attribute value, and nothing in it renders or requests.
		self::assertSame( array(), $this->scanner->find_tags( '<div title="unterminated <iframe src="https://a.example/x"></iframe>', 'iframe' ) );
	}

	public function test_end_tag_boundary_follows_html5(): void {
		// `</iframe foo>` closes; `</iframes>` does not.
		$html = '<iframe src="https://a.example/1">a</iframe foo><iframe src="https://a.example/2">b </iframes> c</iframe>';
		$tags = $this->scanner->find_tags( $html, 'iframe' );

		self::assertCount( 2, $tags );
		self::assertSame( strlen( '<iframe src="https://a.example/1">a</iframe foo>' ), $tags[0]['end'] );
		self::assertSame( strlen( $html ), $tags[1]['end'] );
	}

	public function test_iframe_content_is_raw_text(): void {
		// Browsers never parse an iframe's fallback content as markup, so a
		// script or image in it fires nothing — and is found by no rule.
		$html = '<iframe src="https://a.example/x"><script src="https://b.example/s.js"></script><img src="https://c.example/p.png"></iframe>';

		self::assertSame( array(), $this->scanner->find_tags( $html, 'script' ) );
		self::assertSame( array(), $this->scanner->find_tags( $html, 'img' ) );
		self::assertCount( 1, $this->scanner->find_tags( $html, 'iframe' ) );
	}

	public function test_doctype_cdata_and_end_tags_on_their_own_are_skipped(): void {
		$html = '<!DOCTYPE html><![CDATA[ <iframe src="https://a.example/cdata"> ]]></div><iframe src="https://a.example/x"></iframe>';
		$tags = $this->scanner->find_tags( $html, 'iframe' );

		self::assertCount( 1, $tags );
		self::assertSame( 'https://a.example/x', $tags[0]['attributes']['src'] );
	}
}
