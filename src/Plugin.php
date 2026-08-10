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

use ConsentGate\Admin\SettingsPage;
use ConsentGate\Detection\EmbedStripper;
use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use ConsentGate\Detection\IframeRule;
use ConsentGate\Detection\ScriptRule;
use ConsentGate\Integration\Excerpt;
use ConsentGate\Integration\OutputBuffer;
use ConsentGate\Integration\RenderBlock;
use ConsentGate\Integration\ResourceHints as ResourceHintsIntegration;
use ConsentGate\Integration\TheContent;
use ConsentGate\Integration\Widgets;
use ConsentGate\Integration\WithdrawShortcode;
use ConsentGate\Providers\Builtin\Descriptors;
use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;
use ConsentGate\Rendering\TemplateLoader;
use ConsentGate\Support\CacheFlush;
use ConsentGate\Support\Options;
use ConsentGate\Support\ResourceHints;

/**
 * Builds the pipeline and registers the integrations.
 */
final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var IframeRule */
	private IframeRule $iframe_rule;

	/** @var ScriptRule */
	private ScriptRule $script_rule;

	/** @var array Sanitised option tree. */
	private array $options;

	/** @var EmbedStripper */
	private EmbedStripper $stripper;

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
		$this->options = Options::sanitize( get_option( Options::OPTION, Options::defaults() ) );

		$translate = static function ( string $text ): string {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- bridged strings are extracted where they are defined.
			return __( $text, 'consent-gate' );
		};

		$hosts = new HostMatcher(
			$this->own_hosts(),
			(bool) apply_filters( 'consent_gate_www_equivalence', $this->options['detection']['www_equivalence'] ),
			static function ( bool $own, string $host ): bool {
				return (bool) apply_filters( 'consent_gate_is_own_host', $own, $host );
			}
		);

		$providers = Options::apply_provider_overrides(
			Descriptors::all( $translate ),
			$this->options
		);

		$registry = new Registry(
			(array) apply_filters( 'consent_gate_providers', $providers ),
			$translate
		);

		$renderer = new PlaceholderRenderer(
			$translate,
			static function ( string $html, array $provider, array $ctx ): string {
				return (string) apply_filters( 'consent_gate_placeholder_html', $html, $provider, $ctx );
			},
			static function ( array $payload, array $provider ): array {
				return (array) apply_filters( 'consent_gate_payload', $payload, $provider );
			},
			new TemplateLoader(
				static function ( string $relative ): string {
					return function_exists( 'locate_template' ) ? (string) locate_template( $relative ) : '';
				}
			)
		);

		$scanner     = new HtmlScanner();
		$should_gate = static function ( bool $gate, string $url, array $ctx ): bool {
			return (bool) apply_filters( 'consent_gate_should_gate', $gate, $url, $ctx );
		};
		$on_gated    = function ( array $provider, array $ctx ): void {
			$this->enqueue_assets();
			do_action( 'consent_gate_embed_gated', $provider, $ctx );
		};

		$this->iframe_rule = new IframeRule( $scanner, $hosts, $registry, $renderer, $should_gate, $on_gated );
		$this->script_rule = new ScriptRule( $scanner, $hosts, $registry, $renderer, $should_gate, $on_gated );
		$this->stripper    = new EmbedStripper( $scanner, $hosts );

		add_action(
			'init',
			static function (): void {
				load_plugin_textdomain(
					'consent-gate',
					false,
					dirname( plugin_basename( CONSENT_GATE_FILE ) ) . '/languages'
				);
			}
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		( new RenderBlock( $this ) )->register();
		( new TheContent( $this ) )->register();
		( new Widgets( $this ) )->register();
		( new Excerpt( $this ) )->register();
		( new WithdrawShortcode() )->register();
		( new SettingsPage( $providers ) )->register();
		( new ResourceHintsIntegration( new ResourceHints( $this->provider_hosts( $providers ), $hosts ) ) )->register();

		if ( $this->options['detection']['output_buffer'] ) {
			( new OutputBuffer( $this ) )->register();
		}

		// Cached pages keep serving pre-change markup after a settings save
		// (§9.12) — flush the caches we can reach.
		add_action(
			'update_option_' . Options::OPTION,
			static function (): void {
				CacheFlush::flush_all();
			}
		);
	}

	/**
	 * Run the enabled detection rules over a fragment.
	 *
	 * @param string $html Content.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function gate( string $html, array $ctx ): string {
		if ( $this->options['detection']['iframes'] ) {
			$html = $this->iframe_rule->apply( $html, $ctx );
		}
		if ( $this->options['detection']['scripts'] ) {
			$html = $this->script_rule->apply( $html, $ctx );
		}
		return $html;
	}

	/**
	 * Remove third-party embeds entirely — for excerpts and feeds, where a
	 * placeholder is nonsense (§3.3, §9.3).
	 *
	 * @param string $html Content.
	 * @return string
	 */
	public function strip( string $html ): string {
		return $this->stripper->strip( $html );
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

		// Consent-memory config (§6.2): only shipped when the site enabled
		// memory. The default build stores nothing and needs no config.
		$consent = $this->options['consent'];
		if ( 'off' !== $consent['memory'] ) {
			wp_add_inline_script(
				'consent-gate',
				'window.consentGateConfig = ' . wp_json_encode(
					array(
						'memory'       => $consent['memory'],
						'scope'        => $consent['scope'],
						'durationDays' => $consent['duration_days'],
						'i18n'         => array(
							'withdrawn' => __( 'Stored embed consents have been removed. Embeds will ask again.', 'consent-gate' ),
						),
					)
				) . ';',
				'before'
			);
		}
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
		// Multisite (§9.11): a cross-site embed inside one network is not a
		// third party. Mapped domains appear as each site's domain.
		if ( is_multisite() && function_exists( 'get_sites' ) ) {
			foreach ( get_sites( array( 'number' => 500 ) ) as $site ) {
				if ( isset( $site->domain ) && '' !== $site->domain ) {
					$hosts[] = $site->domain;
				}
			}
		}
		// The configured never-gate list has the same effect as an own host:
		// the embed passes through. Kept as a separate setting because the
		// meaning differs — the owner is accepting those requests.
		$extra = (array) apply_filters(
			'consent_gate_own_hosts',
			array_merge( $this->options['detection']['own_hosts'], $this->options['detection']['never_gate'] )
		);
		return array_values( array_unique( array_merge( $hosts, $extra ) ) );
	}

	/**
	 * Every host the provider match tables know — the set whose resource
	 * hints must not survive (§9.14).
	 *
	 * @param array[] $providers Descriptors.
	 * @return string[]
	 */
	private function provider_hosts( array $providers ): array {
		$hosts = array();
		foreach ( $providers as $descriptor ) {
			$match = isset( $descriptor['match'] ) && is_array( $descriptor['match'] ) ? $descriptor['match'] : array();
			foreach ( array( 'iframe_host', 'script_host' ) as $key ) {
				if ( isset( $match[ $key ] ) ) {
					$hosts = array_merge( $hosts, (array) $match[ $key ] );
				}
			}
		}
		return array_values( array_unique( $hosts ) );
	}
}
