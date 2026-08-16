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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConsentGate\Admin\BlockEditor;
use ConsentGate\Admin\SettingsPage;
use ConsentGate\Cli\Commands as CliCommands;
use ConsentGate\Cmp\BridgeConfig;
use ConsentGate\Cmp\Detector;
use ConsentGate\Detection\EmbedObjectRule;
use ConsentGate\Detection\EmbedStripper;
use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use ConsentGate\Detection\IframeRule;
use ConsentGate\Detection\ImageRule;
use ConsentGate\Detection\ScriptRule;
use ConsentGate\Integration\Comments;
use ConsentGate\Integration\Descriptions;
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
use ConsentGate\Support\ContentScan;
use ConsentGate\Support\Options;
use ConsentGate\Support\ResourceHints;

/**
 * Builds the pipeline and registers the integrations.
 */
final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var IframeRule|null Built lazily; see build_pipeline(). */
	private ?IframeRule $iframe_rule = null;

	/** @var EmbedObjectRule|null */
	private ?EmbedObjectRule $embed_object_rule = null;

	/** @var ScriptRule|null */
	private ?ScriptRule $script_rule = null;

	/** @var ImageRule|null */
	private ?ImageRule $image_rule = null;

	/** @var Registry|null */
	private ?Registry $registry = null;

	/** @var HostMatcher|null */
	private ?HostMatcher $host_matcher = null;

	/** @var array Sanitised option tree. */
	private array $options;

	/** @var EmbedStripper|null */
	private ?EmbedStripper $stripper = null;

	/** @var HtmlScanner|null */
	private ?HtmlScanner $scanner = null;

	/** @var ResourceHints|null */
	private ?ResourceHints $hint_scrubber = null;

	/** @var PlaceholderRenderer|null */
	private ?PlaceholderRenderer $renderer = null;

	/** @var array[]|null Filtered provider descriptors; resolved lazily. */
	private ?array $providers_cache = null;

	/** @var bool True while render_ungated() runs; see should_bail(). */
	private bool $gating_suspended = false;

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

		// No load_plugin_textdomain() call: WordPress ≥ 4.6 loads the
		// wordpress.org language packs for the plugin's text domain
		// automatically, and the plugin ships no .mo files of its own.

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		( new RenderBlock( $this ) )->register();
		( new TheContent( $this ) )->register();
		( new Widgets( $this ) )->register();
		( new Comments( $this ) )->register();
		( new Descriptions( $this ) )->register();
		( new Excerpt( $this ) )->register();
		$withdraw = new WithdrawShortcode(
			function (): void {
				// The withdrawal control's intended home is a privacy-policy
				// page with no embeds — without this enqueue the button is a
				// dead element there (invariant 2's spirit).
				$this->enqueue_assets();
			}
		);
		$withdraw->register();
		( new BlockEditor( $withdraw ) )->register();
		( new SettingsPage(
			function (): array {
				return $this->providers();
			},
			function (): ContentScan {
				return $this->content_scanner();
			},
			function (): string {
				return $this->preview_placeholder_html();
			}
		) )->register();
		( new ResourceHintsIntegration(
			function (): ResourceHints {
				$this->build_pipeline();
				return $this->hint_scrubber;
			}
		) )->register();

		// Read-only inspection for shells, CI and AI agents (docs/customizing.md):
		// the Status screen's answers without wp-admin.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command(
				'consent-gate',
				new CliCommands(
					function (): array {
						return $this->providers();
					},
					function (): ContentScan {
						return $this->content_scanner();
					},
					function ( string $content ): string {
						return $this->render_ungated( $content );
					}
				)
			);
		}

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

		// Deactivation must restore original behaviour immediately (§9.10);
		// a page cache still serving placeholders would reference assets
		// that no longer load. Flush what we can reach.
		register_deactivation_hook(
			CONSENT_GATE_FILE,
			static function (): void {
				CacheFlush::flush_all();
			}
		);
	}

	/**
	 * The filtered provider set — the ONE set every consumer shares.
	 *
	 * Resolved lazily, not at plugins_loaded: the documented way to add a
	 * provider is "a ten-line filter in functions.php", and a theme's
	 * functions.php loads AFTER plugins_loaded. Resolving here (first use is
	 * during rendering or in the admin) means theme-registered providers
	 * reach the registry, the settings table, the CSP snippet and the
	 * resource-hint scrubber alike — previously the last three saw only the
	 * unfiltered builtins.
	 *
	 * @return array[]
	 */
	private function providers(): array {
		if ( null === $this->providers_cache ) {
			$this->providers_cache = (array) apply_filters(
				'consent_gate_providers',
				Options::apply_provider_overrides(
					Descriptors::all( $this->translator() ),
					$this->options
				)
			);
		}
		return $this->providers_cache;
	}

	/**
	 * @return callable Bridges English strings to the site language.
	 */
	private function translator(): callable {
		return static function ( string $text ): string {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- bridged strings are defined literally at their $t() call sites and mirrored as literal __() calls in languages/strings.php for the translation parser.
			return __( $text, 'third-party-embed-gate' );
		};
	}

	/**
	 * Build the detection/render pipeline once, on first use. Lazy for the
	 * same reason as providers(), and because own_hosts() reads home_url()
	 * — at plugins_loaded, domain-mapping and multilingual plugins have not
	 * registered their host filters yet (§9.11).
	 *
	 * @return void
	 */
	private function build_pipeline(): void {
		if ( null !== $this->iframe_rule ) {
			return;
		}

		$translate = $this->translator();

		$always_gate = $this->options['detection']['always_gate'];

		$hosts = new HostMatcher(
			$this->own_hosts(),
			(bool) apply_filters( 'consent_gate_www_equivalence', $this->options['detection']['www_equivalence'] ),
			static function ( bool $own, string $host ) use ( $always_gate ): bool {
				// The always-gate list wins over every own-host rule: a
				// subdomain of the site's own domain that serves trackers is
				// exactly what it exists for (§7.1).
				if ( HostMatcher::host_matches_list( $host, $always_gate ) ) {
					return false;
				}
				return (bool) apply_filters( 'consent_gate_is_own_host', $own, $host );
			}
		);

		$providers = $this->providers();

		$registry = new Registry(
			$providers,
			$translate,
			static function ( array $provider, string $url, string $host ): array {
				return (array) apply_filters( 'consent_gate_provider_for_url', $provider, $url, $host );
			}
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
			),
			array(
				'before'   => static function ( array $provider, array $ctx ): void {
					do_action( 'consent_gate_before_render', $provider, $ctx );
				},
				'note'     => static function ( string $note, array $provider, array $ctx ): string {
					return (string) apply_filters( 'consent_gate_note_text', $note, $provider, $ctx );
				},
				'action'   => static function ( string $action, array $provider, array $ctx ): string {
					return (string) apply_filters( 'consent_gate_action_text', $action, $provider, $ctx );
				},
				'fallback' => static function ( string $url, array $provider, array $ctx ): string {
					return (string) apply_filters( 'consent_gate_fallback_url', $url, $provider, $ctx );
				},
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

		$this->scanner           = $scanner;
		$this->registry          = $registry;
		$this->renderer          = $renderer;
		$this->host_matcher      = $hosts;
		$this->iframe_rule       = new IframeRule( $scanner, $hosts, $registry, $renderer, $should_gate, $on_gated );
		$this->embed_object_rule = new EmbedObjectRule( $scanner, $hosts, $registry, $renderer, $should_gate, $on_gated );
		$this->script_rule       = new ScriptRule( $scanner, $hosts, $registry, $renderer, $should_gate, $on_gated );
		$this->image_rule        = new ImageRule( $scanner, $hosts, $registry, $renderer, $should_gate, $on_gated );
		$this->stripper          = new EmbedStripper( $scanner, $hosts, $registry, $translate );
		$this->hint_scrubber     = new ResourceHints( $this->provider_hosts( $providers ), $hosts );
	}

	/**
	 * A sample placeholder for the settings screen's live preview (§7.1).
	 *
	 * Rendered through the real pipeline — theme template overrides, text
	 * filters and all — so the preview cannot drift from what visitors see.
	 * The markup is inert data: gate.js is not enqueued in the admin, and
	 * admin-appearance.js suppresses the panel's link navigation.
	 *
	 * @return string
	 */
	public function preview_placeholder_html(): string {
		$this->build_pipeline();
		$url      = 'https://www.youtube.com/embed/preview';
		$provider = $this->registry->resolve_for_url( $url, 'www.youtube.com' );

		return $this->renderer->render(
			$provider,
			$url,
			array(
				'width'  => '480',
				'height' => '270',
				'title'  => __( 'Example embed', 'third-party-embed-gate' ),
			),
			array( 'integration' => 'admin-preview' )
		);
	}

	/**
	 * Render content through the_content with this plugin's gating
	 * suspended: what the front end WOULD serve without Third-Party Embed Gate.
	 *
	 * The scanner must see original markup to classify it — in wp-admin
	 * should_bail() already guarantees that, but WP-CLI is neither admin
	 * nor front end, and rendering there would gate the iframes into
	 * placeholders the scanner cannot see.
	 *
	 * @param string $content Raw post content.
	 * @return string Rendered HTML, ungated.
	 */
	public function render_ungated( string $content ): string {
		$this->gating_suspended = true;
		try {
			return (string) apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- deliberately rendering through core's own content pipeline so embeds appear as they would on the front end.
		} finally {
			$this->gating_suspended = false;
		}
	}

	/**
	 * The read-only content scanner behind the Status screen (§7.1).
	 *
	 * @return ContentScan
	 */
	public function content_scanner(): ContentScan {
		$this->build_pipeline();
		return new ContentScan(
			$this->scanner,
			$this->host_matcher,
			$this->registry,
			array(
				'iframes' => $this->options['detection']['iframes'],
				'scripts' => $this->options['detection']['scripts'],
				'images'  => $this->options['detection']['images'],
			)
		);
	}

	/**
	 * Cheap pre-parse probe shared by every integration (PLAN.md §9.16):
	 * whether a fragment can contain anything gateable at all. Must name
	 * every tag a detection rule handles — a probe that misses a tag makes
	 * the integration skip the rule silently. '<img' joins only when the
	 * opt-in image rule is on: it is by far the most common tag, and probing
	 * it unconditionally would defeat the fast path everywhere.
	 *
	 * @param string $html Content.
	 * @return bool
	 */
	public function has_gateable_markup( string $html ): bool {
		$probes = array( '<iframe', '<script', '<embed', '<object' );
		if ( $this->options['detection']['images'] ) {
			$probes[] = '<img';
		}
		foreach ( $probes as $probe ) {
			if ( false !== stripos( $html, $probe ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Run the enabled detection rules over a fragment.
	 *
	 * @param string $html Content.
	 * @param array  $ctx  Integration context.
	 * @return string
	 */
	public function gate( string $html, array $ctx ): string {
		$this->build_pipeline();
		if ( $this->options['detection']['iframes'] ) {
			$html = $this->iframe_rule->apply( $html, $ctx );
			// <embed>/<object> are frame-shaped embeds under the same toggle:
			// Flash-era markup requests third-party content on load too.
			$html = $this->embed_object_rule->apply( $html, $ctx );
		}
		if ( $this->options['detection']['scripts'] ) {
			$html = $this->script_rule->apply( $html, $ctx );
		}
		if ( $this->options['detection']['images'] ) {
			$html = $this->image_rule->apply( $html, $ctx );
		}
		return $html;
	}

	/**
	 * Resolve a media-library attachment to a poster URL for the placeholder
	 * (PLAN.md §5.4, owner-supplied variant — the auto-fetch variant was
	 * rejected: no outbound requests, and a cached provider thumbnail goes
	 * stale silently).
	 *
	 * Fail closed: only a URL that classifies as the site's own host is
	 * returned. An offloading plugin that rewrites attachment URLs to a CDN
	 * the site has not declared as an own host would otherwise put a
	 * third-party request inside the placeholder — the exact request the
	 * placeholder exists to prevent (invariant 1). No poster beats that.
	 *
	 * @param int $attachment_id Media library attachment ID.
	 * @return string Poster URL, '' when unusable.
	 */
	public function poster_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		$this->build_pipeline();
		if ( HostMatcher::OWN !== $this->host_matcher->classify( $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Remove third-party embeds entirely — for excerpts and feeds, where a
	 * placeholder is nonsense (§3.3, §9.3).
	 *
	 * @param string $html Content.
	 * @return string
	 */
	public function strip( string $html ): string {
		$this->build_pipeline();
		return $this->stripper->strip( $html );
	}

	/**
	 * Remove literal <link> hint tags for gated hosts — performance plugins
	 * and themes print these directly, bypassing wp_resource_hints (§9.14).
	 * Used by the output buffer, the only place the whole document exists.
	 *
	 * @param string $html Document HTML.
	 * @return string
	 */
	public function scrub_hint_tags( string $html ): string {
		$this->build_pipeline();
		return $this->hint_scrubber->scrub_tags( $html, $this->scanner );
	}

	/**
	 * Never gate where an editor is looking (invariant 4) or where a
	 * placeholder is nonsense (PLAN.md §9.2, §9.3).
	 *
	 * AJAX and REST are deliberately NOT blanket-bailed: infinite scroll,
	 * "load more" and AJAX product filters deliver front-end content over
	 * admin-ajax.php and /wp-json/ to anonymous visitors, and a blanket bail
	 * injects raw third-party iframes into live pages — page two of an
	 * infinite-scroll archive was simply unprotected. The discriminator is
	 * the requester: editors fetch raw content to edit it (the block
	 * renderer, page-builder edit modes), and every editor request is
	 * authenticated with edit capability. Anonymous requests are visitors,
	 * and visitors get gated markup (§9.2).
	 *
	 * @return bool
	 */
	public function should_bail(): bool {
		if ( $this->gating_suspended ) {
			return true;
		}
		if ( wp_doing_ajax() ) {
			return current_user_can( 'edit_posts' );
		}
		if ( is_admin() || is_customize_preview() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return current_user_can( 'edit_posts' );
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return current_user_can( 'edit_posts' );
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
		// The §6.4 bridge is a separate file so the default build (bridge
		// off) ships not a byte of CMP code to visitors.
		wp_register_script(
			'consent-gate-cmp',
			plugins_url( 'assets/js/cmp-bridge.js', CONSENT_GATE_FILE ),
			array( 'consent-gate' ),
			CONSENT_GATE_VERSION,
			true
		);

		// Consent-memory config (§6.2): only shipped when the site enabled
		// memory. The default build stores nothing and needs no config.
		$config = $this->inline_config_json();
		if ( null !== $config ) {
			wp_add_inline_script(
				'consent-gate',
				'window.consentGateConfig = ' . $config . ';',
				'before'
			);
		}

		$appearance = $this->appearance_css();
		if ( '' !== $appearance ) {
			wp_add_inline_style( 'consent-gate', $appearance );
		}
	}

	/**
	 * CSS for the Appearance settings (§7.1): preset + colour overrides of
	 * the §7.3 custom properties. '' when everything is at defaults.
	 *
	 * @return string
	 */
	public function appearance_css(): string {
		$a    = $this->options['appearance'];
		$vars = '';
		foreach ( array(
			'bg'        => '--cg-bg',
			'fg'        => '--cg-fg',
			'accent'    => '--cg-accent',
			'accent_fg' => '--cg-accent-fg',
		) as $option_key => $property ) {
			if ( '' !== $a[ $option_key ] ) {
				$vars .= $property . ':' . $a[ $option_key ] . ';';
			}
		}

		$css = '';
		if ( '' !== $vars ) {
			$css .= '.cg-embed{' . $vars . '}';
		}
		if ( 'minimal' === $a['preset'] ) {
			// Transparent panel on the page's own background; --cg-fg
			// defaults to the theme's contrast preset, so text keeps its
			// ratio against the page.
			$css .= '.cg-embed:not(.cg-embed--active){background:transparent;border:1px solid var(--cg-fg);}';
		} elseif ( 'card' === $a['preset'] ) {
			$css .= '.cg-embed:not(.cg-embed--active){border:1px solid rgba(0,0,0,0.12);border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.18);}';
		}

		// Emitted after the preset rules at equal specificity, so an explicit
		// corner choice beats the card preset's radius. The admin preview
		// (assets/js/admin-appearance.js) mirrors these values inline —
		// change them in both places.
		$radii = array(
			'square'  => '0',
			'rounded' => '12px',
			'pill'    => '12px',
		);
		if ( isset( $radii[ $a['corners'] ] ) ) {
			$radius = $radii[ $a['corners'] ];
			$css   .= '.cg-embed{--cg-radius:' . $radius . ';}.cg-embed:not(.cg-embed--active){border-radius:' . $radius . ';}';
			if ( 'pill' === $a['corners'] ) {
				$css .= '.cg-embed .cg-embed__button{border-radius:999px;}';
			}
		}

		return $css;
	}

	/**
	 * The consentGateConfig JSON. Shared by the enqueue path and the
	 * output-buffer path, which injects tags directly because it runs after
	 * wp_footer. Always present: the loading/error announcements (§8) must
	 * be translatable even when consent memory is off.
	 *
	 * @return string|null
	 */
	public function inline_config_json(): ?string {
		$consent = $this->options['consent'];
		$config  = array(
			'i18n' => array(
				'withdrawn' => __( 'Stored embed consents have been removed. Embeds will ask again.', 'third-party-embed-gate' ),
				'loading'   => __( 'Loading embedded content…', 'third-party-embed-gate' ),
				'error'     => __( 'The embedded content could not be loaded.', 'third-party-embed-gate' ),
				'errorLink' => __( 'Open it on the provider’s site.', 'third-party-embed-gate' ),
			),
		);
		if ( 'off' !== $consent['memory'] ) {
			$config['memory']       = $consent['memory'];
			$config['scope']        = $consent['scope'];
			$config['durationDays'] = $consent['duration_days'];
		}
		$cmp = $this->cmp_bridge_config();
		if ( null !== $cmp ) {
			$config['cmp'] = $cmp;
		}
		// Emitted verbatim inside an inline <script> (enqueue path and the
		// output-buffer shutdown path). Default json_encode already escapes
		// '/', so '</script>' cannot break out; JSON_HEX_TAG|APOS|QUOT|AMP is
		// belt-and-braces consistency with the data-cg-payload path (§9.1) so
		// no config string — i18n, a filtered CMP category — can ever inject
		// markup, matching esc_json()'s guarantees.
		return (string) wp_json_encode(
			$config,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
	}

	/**
	 * The §6.4 bridge config, or null when the bridge stays off — because
	 * the option is off, or because no platform from the tested list is
	 * installed (fail closed; an untested CMP gets no adapter).
	 *
	 * The filter exists for the documented escape hatches: overriding the
	 * category a site's CMP files embeds under, or adding TCF vendor ids
	 * for custom providers. Returning null (or anything non-array) from it
	 * disables the bridge entirely.
	 *
	 * @return array|null
	 */
	public function cmp_bridge_config(): ?array {
		$config = BridgeConfig::build( Detector::detected(), $this->options['cmp'] );
		$config = apply_filters( 'consent_gate_cmp_config', $config, $this->options['cmp'] );
		return is_array( $config ) ? $config : null;
	}

	/**
	 * @return void
	 */
	private function enqueue_assets(): void {
		wp_enqueue_script( 'consent-gate' );
		wp_enqueue_style( 'consent-gate' );
		if ( null !== $this->cmp_bridge_config() ) {
			wp_enqueue_script( 'consent-gate-cmp' );
		}
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
	 * Every host the provider match tables know — plus each provider's
	 * declared sibling CDN hosts (i.ytimg.com, pbs.twimg.com) — the set
	 * whose resource hints must not survive (§9.14).
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
			if ( isset( $descriptor['hint_hosts'] ) ) {
				$hosts = array_merge( $hosts, (array) $descriptor['hint_hosts'] );
			}
		}
		return array_values( array_unique( $hosts ) );
	}
}
