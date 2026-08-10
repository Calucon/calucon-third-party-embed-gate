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
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

use ConsentGate\Plugin;

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
		if ( $this->plugin->should_bail() || is_feed() ) {
			return;
		}
		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
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
			return $this->plugin->gate(
				$buffer,
				array(
					'integration' => 'output_buffer',
					'block'       => null,
					'post_id'     => null,
				)
			);
		} catch ( \Throwable $e ) {
			// A fatal inside an output callback yields a blank page;
			// unmodified output is always the better failure.
			return $buffer;
		}
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
