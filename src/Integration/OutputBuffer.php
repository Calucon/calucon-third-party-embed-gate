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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

			$buffer = substr( $buffer, 0, $body[0] ) . $gated . substr( $buffer, $body[1] );

			// This callback runs on shutdown, after wp_footer has already
			// been rendered INTO the buffer — wp_enqueue_* is a no-op here.
			// Without direct injection every placeholder this pass created
			// would be an unstyled panel with a dead button on exactly the
			// page-builder sites this option exists for (invariant 2's
			// "never a button that does nothing").
			return $this->inject_assets( $buffer );
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
		$open = stripos( $buffer, '<body' );
		if ( false === $open ) {
			return array( 0, strlen( $buffer ) );
		}
		$open_end = strpos( $buffer, '>', $open );
		if ( false === $open_end ) {
			return array( 0, strlen( $buffer ) );
		}
		$close = strripos( $buffer, '</body' );
		if ( false === $close || $close < $open_end ) {
			$close = strlen( $buffer );
		}
		return array( $open_end + 1, $close );
	}

	/**
	 * Inject the plugin's CSS and JS directly into the buffered document,
	 * unless the normal enqueue already printed them (a the_content-gated
	 * embed on the same page).
	 *
	 * @param string $buffer Gated document.
	 * @return string
	 */
	private function inject_assets( string $buffer ): string {
		$version = rawurlencode( CONSENT_GATE_VERSION );

		if ( false === strpos( $buffer, 'id="consent-gate-css"' )
			&& false === strpos( $buffer, "id='consent-gate-css'" ) ) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- this runs on shutdown, after wp_footer was rendered into the buffer; wp_enqueue_style() is a no-op here.
			$css        = '<link rel="stylesheet" id="consent-gate-css" href="'
				. esc_url( plugins_url( 'assets/css/gate.css', CONSENT_GATE_FILE ) . '?ver=' . $version )
				. '" media="all">';
			$appearance = $this->plugin->appearance_css();
			if ( '' !== $appearance ) {
				$css .= '<style id="consent-gate-inline-css">' . $appearance . '</style>';
			}
			$head = stripos( $buffer, '</head>' );
			if ( false !== $head ) {
				$buffer = substr( $buffer, 0, $head ) . $css . substr( $buffer, $head );
			} else {
				$buffer = $css . $buffer;
			}
		}

		if ( false === strpos( $buffer, 'id="consent-gate-js"' )
			&& false === strpos( $buffer, "id='consent-gate-js'" ) ) {
			$config = $this->plugin->inline_config_json();
			$js     = '';
			if ( null !== $config ) {
				$js .= '<script id="consent-gate-js-before">window.consentGateConfig = ' . $config . ';</script>';
			}
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- this runs on shutdown, after wp_footer was rendered into the buffer; wp_enqueue_script() is a no-op here.
			$js .= '<script id="consent-gate-js" src="'
				. esc_url( plugins_url( 'assets/js/gate.js', CONSENT_GATE_FILE ) . '?ver=' . $version )
				. '" defer></script>';
			if ( null !== $this->plugin->cmp_bridge_config() ) {
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- same shutdown constraint as gate.js above.
				$js .= '<script id="consent-gate-cmp-js" src="'
					. esc_url( plugins_url( 'assets/js/cmp-bridge.js', CONSENT_GATE_FILE ) . '?ver=' . $version )
					. '" defer></script>';
			}
			$foot = strripos( $buffer, '</body>' );
			if ( false !== $foot ) {
				$buffer = substr( $buffer, 0, $foot ) . $js . substr( $buffer, $foot );
			} else {
				$buffer .= $js;
			}
		}

		return $buffer;
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
