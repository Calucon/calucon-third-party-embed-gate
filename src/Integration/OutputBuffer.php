<?php
/**
 * Output-buffer integration for page builders (PLAN.md §3.3).
 *
 * Elementor, Divi, WPBakery and Bricks render outside the content filters,
 * so for those sites nothing else works. A whole-document buffer is
 * invasive, so this is opt-in only, guarded on every side:
 *
 * - Off by default; enabled via a clearly-labelled setting with a warning.
 * - Registered late (template_redirect, low priority), closed on shutdown.
 * - Any exception returns the buffer unmodified — never a blank page.
 * - Skipped for non-HTML responses, feeds, REST, AJAX, embeds, sitemaps.
 * - Checks ob_get_level() and never assumes it owns the stack.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Detection\HtmlScanner;
use CaluconEmbedGate\Plugin;

/**
 * Whole-document gating for sites where content filters never fire.
 */
final class OutputBuffer {

	/** @var Plugin */
	private Plugin $plugin;

	/** @var int|null ob_get_level() before we started, null when inactive. */
	private ?int $level_before = null;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'start' ), 9999 );
		add_action( 'shutdown', array( $this, 'finish' ), 0 );
	}

	/**
	 * @return void
	 */
	public function start(): void {
		if ( $this->plugin->should_bail() ) { // should_bail() already covers feeds.
			return;
		}
		// REST responses are not HTML pages to gate. Use the core REST_REQUEST
		// constant, defined on every supported version, rather than the
		// dedicated REST-request helper added only in WordPress 6.5.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( headers_sent() ) {
			return; // Response already committed; too late to buffer safely.
		}

		$this->level_before = ob_get_level();
		ob_start( array( $this, 'process' ) );
	}

	/**
	 * @return void
	 */
	public function finish(): void {
		if ( null === $this->level_before ) {
			return;
		}
		// Close only the buffer we opened; other plugins may have stacked
		// their own above ours — flush down to our level, no further.
		while ( ob_get_level() > $this->level_before ) {
			if ( ! @ob_end_flush() ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a non-flushable buffer must not fatal on shutdown.
				break;
			}
		}
		$this->level_before = null;
	}

	/**
	 * @param string $buffer Whole document.
	 * @return string
	 */
	public function process( $buffer ): string {
		$buffer = (string) $buffer;

		try {
			if ( ! $this->looks_like_html( $buffer ) ) {
				return $buffer;
			}

			// Hint tags printed directly by themes/performance plugins bypass
			// the wp_resource_hints filter; the buffer is the only place the
			// whole document (head included) is in hand to scrub them (§9.14).
			$buffer = $this->plugin->scrub_hint_tags( $buffer );

			// Gate only inside <body>. The head carries the site's own
			// enqueued scripts (analytics, tag managers, payment SDKs) and a
			// visible panel there is invalid markup that breaks the page —
			// this integration exists for page-builder CONTENT, which lives
			// in the body. Head findings are a job for the admin status
			// screen, not for a silent rewrite.
			$body = $this->body_span( $buffer );
			$html = substr( $buffer, $body[0], $body[1] - $body[0] );

			$gated = $this->plugin->gate(
				$html,
				array(
					'integration' => 'output_buffer',
					'block'       => null,
					'post_id'     => null,
				)
			);

			if ( $gated === $html ) {
				return $buffer;
			}

			// The placeholder's CSS/JS are already inside the buffer: while
			// this option is enabled, Assets::register_assets() enqueues them
			// on every front-end page (this callback runs on shutdown, after
			// wp_footer — far too late to enqueue anything).
			return substr( $buffer, 0, $body[0] ) . $gated . substr( $buffer, $body[1] );
		} catch ( \Throwable $e ) {
			// A fatal inside an output callback yields a blank page;
			// unmodified output is always the better failure.
			return $buffer;
		}
	}

	/**
	 * The [start, end) span of the body content, or the whole buffer when no
	 * body element is found (fragment responses).
	 *
	 * @param string $buffer Whole document.
	 * @return array{0:int,1:int}
	 */
	private function body_span( string $buffer ): array {
		// A tag boundary after the name (`<bodyguard` is not a body), and the
		// start tag's end read by the attribute-aware scanner rather than the
		// first '>' — a '>' inside a quoted attribute value would otherwise
		// put the split point mid-tag.
		if ( ! preg_match( '/<body(?=[\s\/>])/i', $buffer, $m, PREG_OFFSET_CAPTURE ) ) {
			return array( 0, strlen( $buffer ) );
		}
		$open     = (int) $m[0][1];
		$open_end = ( new HtmlScanner() )->start_tag_end( $buffer, $open );
		if ( null === $open_end ) {
			return array( 0, strlen( $buffer ) );
		}
		$close = strripos( $buffer, '</body' );
		if ( false === $close || $close < $open_end ) {
			$close = strlen( $buffer );
		}
		return array( $open_end, $close );
	}


	/**
	 * @param string $buffer Response body.
	 * @return bool
	 */
	private function looks_like_html( string $buffer ): bool {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) ) {
				return false !== stripos( $header, 'text/html' );
			}
		}
		// No explicit content type: PHP defaults to text/html.
		return '' !== $buffer && false !== stripos( substr( ltrim( $buffer ), 0, 200 ), '<' );
	}
}
