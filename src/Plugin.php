<?php
/**
 * Wiring; no logic (PLAN.md §2.2).
 *
 * This is the only place (besides Integration/ and Admin/) where WordPress
 * globals may appear. Detection/, Providers/ and Rendering/ receive plain
 * callables bridging to WordPress filters and i18n.
 *
 * @package ConsentGate
 */

namespace ConsentGate;

use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use ConsentGate\Detection\IframeRule;
use ConsentGate\Integration\RenderBlock;
use ConsentGate\Integration\TheContent;
use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;

/**
 * Builds the pipeline and registers the integrations.
 */
final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var IframeRule */
	private IframeRule $rule;

	/**
	 * Bootstraps the plugin once, on plugins_loaded.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	/**
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		self::boot();
		return self::$instance;
	}

	private function __construct() {
		$translate = static function ( string $text ): string {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- bridged strings are extracted where they are defined.
			return __( $text, 'consent-gate' );
		};

		$hosts = new HostMatcher(
			$this->own_hosts(),
			(bool) apply_filters( 'consent_gate_www_equivalence', true ),
			static function ( bool $own, string $host ): bool {
				return (bool) apply_filters( 'consent_gate_is_own_host', $own, $host );
			}
		);

		$registry = new Registry(
			(array) apply_filters( 'consent_gate_providers', array() ),
			$translate
		);

		$renderer = new PlaceholderRenderer(
			$translate,
			static function ( string $html, array $provider, array $ctx ): string {
				return (string) apply_filters( 'consent_gate_placeholder_html', $html, $provider, $ctx );
			},
			static function ( array $payload, array $provider ): array {
				return (array) apply_filters( 'consent_gate_payload', $payload, $provider );
			}
		);

		$this->rule = new IframeRule(
			new HtmlScanner(),
			$hosts,
			$registry,
			$renderer,
			static function ( bool $gate, string $url, array $ctx ): bool {
				return (bool) apply_filters( 'consent_gate_should_gate', $gate, $url, $ctx );
			},
			function ( array $provider, array $ctx ): void {
				$this->enqueue_assets();
				do_action( 'consent_gate_embed_gated', $provider, $ctx );
			}
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		( new RenderBlock( $this ) )->register();
		( new TheContent( $this ) )->register();
	}

	/**
	 * Run the iframe rule over a fragment.
	 *
	 * @param string $html Content.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function gate( string $html, array $ctx ): string {
		return $this->rule->apply( $html, $ctx );
	}

	/**
	 * Never gate where an editor is looking (invariant 4) or where a
	 * placeholder is nonsense (PLAN.md §9.2, §9.3).
	 *
	 * @return bool
	 */
	public function should_bail(): bool {
		if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context probe.
		if ( isset( $_GET['context'] ) && 'edit' === $_GET['context'] ) {
			return true;
		}
		if ( is_feed() || is_embed() ) {
			return true;
		}
		return false;
	}

	/**
	 * Register front-end assets; they are only enqueued when a placeholder
	 * was actually rendered, so pages without embeds ship no extra bytes.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_script(
			'consent-gate',
			plugins_url( 'assets/js/gate.js', CONSENT_GATE_FILE ),
			array(),
			CONSENT_GATE_VERSION,
			true
		);
		wp_register_style(
			'consent-gate',
			plugins_url( 'assets/css/gate.css', CONSENT_GATE_FILE ),
			array(),
			CONSENT_GATE_VERSION
		);
	}

	/**
	 * @return void
	 */
	private function enqueue_assets(): void {
		wp_enqueue_script( 'consent-gate' );
		wp_enqueue_style( 'consent-gate' );
	}

	/**
	 * Hosts that count as the site itself. Naive home_url() comparison is
	 * wrong on real sites (PLAN.md §3.4): include site_url() for
	 * WordPress-in-a-subdirectory, and let sites declare their CDN via the
	 * consent_gate_own_hosts filter.
	 *
	 * @return string[]
	 */
	private function own_hosts(): array {
		$hosts = array();
		foreach ( array( home_url(), site_url() ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$hosts[] = $host;
			}
		}
		$extra = (array) apply_filters( 'consent_gate_own_hosts', array() );
		return array_values( array_unique( array_merge( $hosts, $extra ) ) );
	}
}
