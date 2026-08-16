<?php
/**
 * Settings screen (PLAN.md §7.1, M3 subset: Providers + Detection).
 *
 * Admin/ is allowed to use WordPress globals (PLAN.md §2.2). Everything
 * user-submitted goes through Options::sanitize(); everything printed goes
 * through esc_*(); the form is nonce-protected by the Settings API.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Cmp\Detector;
use CaluconEmbedGate\Support\Csp;
use CaluconEmbedGate\Support\Options;

/**
 * Settings > Calucon Third-Party Embed Gate.
 */
final class SettingsPage {

	/** @var callable Returns the provider descriptors; resolved lazily so
	 *                providers registered by the theme's functions.php (which
	 *                loads after plugins_loaded) appear in the table and the
	 *                CSP snippet. */
	private $providers_source;

	/** @var callable|null Returns the ContentScan behind the Status screen. */
	private $scanner_source;

	/** @var callable|null Returns sample placeholder HTML for the live preview. */
	private $preview_source;

	/**
	 * @param callable      $providers_source fn(): array[] — builtins + filtered.
	 * @param callable|null $scanner_source   fn(): \CaluconEmbedGate\Support\ContentScan.
	 * @param callable|null $preview_source   fn(): string — rendered sample placeholder.
	 */
	public function __construct( callable $providers_source, ?callable $scanner_source = null, ?callable $preview_source = null ) {
		$this->providers_source = $providers_source;
		$this->scanner_source   = $scanner_source;
		$this->preview_source   = $preview_source;
	}

	/**
	 * @return array[]
	 */
	private function providers(): array {
		return (array) call_user_func( $this->providers_source );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_footer_text', array( $this, 'footer_support_link' ) );
	}

	/**
	 * A single, unobtrusive support link in the admin footer — shown only on
	 * this plugin's own settings screen, never elsewhere in wp-admin. A plain
	 * link, not a Ko-fi widget or remote badge: nothing off-site loads (the
	 * plugin's no-outbound-request principle applies to its own admin UI too),
	 * the browser only contacts Ko-fi if the owner clicks.
	 *
	 * @param string $text The default footer text.
	 * @return string
	 */
	public function footer_support_link( $text ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || 'settings_page_calucon-embed-gate' !== $screen->id ) {
			return (string) $text;
		}

		// No emoji here: WordPress's emoji script would replace it with an
		// <img> fetched from s.w.org — an outbound request, which this plugin
		// does not make, not even from its own admin screen.
		$link = '<a href="https://ko-fi.com/calucon" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'support its development', 'calucon-third-party-embed-gate' ) . '</a>';

		/* translators: %s: link reading "support its development", to the developer's Ko-fi page. */
		return sprintf( esc_html__( 'Calucon Third-Party Embed Gate is free and open source — you can %s.', 'calucon-third-party-embed-gate' ), $link );
	}

	/**
	 * Assets for the Appearance controls: WordPress's own colour picker, the
	 * front-end panel stylesheet (so the live preview IS the real panel) and
	 * the preview/contrast script. Settings screen only — everything is
	 * bundled or core, nothing remote (invariant 9).
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_calucon-embed-gate' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style(
			'calucon-embed-gate',
			plugins_url( 'assets/css/gate.css', CALUCON_EMBED_GATE_FILE ),
			array(),
			CALUCON_EMBED_GATE_VERSION
		);
		wp_enqueue_style(
			'calucon-embed-gate-admin',
			plugins_url( 'assets/css/admin-appearance.css', CALUCON_EMBED_GATE_FILE ),
			array( 'calucon-embed-gate' ),
			CALUCON_EMBED_GATE_VERSION
		);
		wp_enqueue_script(
			'calucon-embed-gate-admin',
			plugins_url( 'assets/js/admin-appearance.js', CALUCON_EMBED_GATE_FILE ),
			array( 'jquery', 'wp-color-picker' ),
			CALUCON_EMBED_GATE_VERSION,
			true
		);
		wp_enqueue_script(
			'calucon-embed-gate-admin-tabs',
			plugins_url( 'assets/js/admin-tabs.js', CALUCON_EMBED_GATE_FILE ),
			array(),
			CALUCON_EMBED_GATE_VERSION,
			true
		);
		wp_add_inline_script(
			'calucon-embed-gate-admin',
			'window.caluconEmbedGateAdminI18n = ' . wp_json_encode(
				array(
					/* translators: contrast-report line. 1: which colour pair, 2: measured ratio like "4.9:1", 3: verdict. */
					'line'       => __( '%1$s: %2$s — %3$s', 'calucon-third-party-embed-gate' ),
					'panelText'  => __( 'Panel text on the panel background', 'calucon-third-party-embed-gate' ),
					'buttonText' => __( 'Button text on the button background', 'calucon-third-party-embed-gate' ),
					'linkText'   => __( 'Fallback link on the panel background', 'calucon-third-party-embed-gate' ),
					'pass'       => __( 'readable (meets the 4.5:1 minimum)', 'calucon-third-party-embed-gate' ),
					'fail'       => __( 'hard to read — below the 4.5:1 minimum. Pick a lighter or darker colour for this pair.', 'calucon-third-party-embed-gate' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * @return void
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'Calucon Third-Party Embed Gate', 'calucon-third-party-embed-gate' ),
			__( 'Calucon Third-Party Embed Gate', 'calucon-third-party-embed-gate' ),
			'manage_options',
			'calucon-embed-gate',
			array( $this, 'render' )
		);
	}

	/**
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			'calucon_embed_gate',
			Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Options::class, 'sanitize' ),
				'default'           => Options::defaults(),
			)
		);
	}

	/**
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options   = Options::sanitize( get_option( Options::OPTION, Options::defaults() ) );
		$providers = $options['providers'];
		$detection = $options['detection'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Calucon Third-Party Embed Gate', 'calucon-third-party-embed-gate' ); ?></h1>
			<p><?php esc_html_e( 'Third-party embeds are replaced with a placeholder until the visitor clicks to load them. Nothing is contacted, and nothing is stored, before that click.', 'calucon-third-party-embed-gate' ); ?></p>

			<?php
			// The tab bar starts hidden and is revealed by admin-tabs.js:
			// without JavaScript the page renders as one long document, every
			// panel visible — tabs are an enhancement, never a gate.
			$tabs = array(
				'providers'  => __( 'Providers', 'calucon-third-party-embed-gate' ),
				'detection'  => __( 'Detection', 'calucon-third-party-embed-gate' ),
				'appearance' => __( 'Appearance', 'calucon-third-party-embed-gate' ),
				'consent'    => __( 'Consent memory', 'calucon-third-party-embed-gate' ),
				'status'     => __( 'Status & tools', 'calucon-third-party-embed-gate' ),
			);
			?>
			<div class="nav-tab-wrapper cg-tabs" role="tablist" aria-label="<?php echo esc_attr( __( 'Calucon Third-Party Embed Gate settings sections', 'calucon-third-party-embed-gate' ) ); ?>" hidden>
				<?php $first = true; ?>
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<button type="button" id="cg-tabbtn-<?php echo esc_attr( $tab_key ); ?>" class="nav-tab<?php echo $first ? ' nav-tab-active' : ''; ?>" role="tab" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" aria-controls="cg-tab-<?php echo esc_attr( $tab_key ); ?>" tabindex="<?php echo $first ? '0' : '-1'; ?>"><?php echo esc_html( $tab_label ); ?></button>
					<?php $first = false; ?>
				<?php endforeach; ?>
			</div>

			<form action="options.php" method="post">
				<?php settings_fields( 'calucon_embed_gate' ); ?>

				<div id="cg-tab-providers" class="cg-tab-panel" role="tabpanel" aria-labelledby="cg-tabbtn-providers">
				<h2><?php esc_html_e( 'Providers', 'calucon-third-party-embed-gate' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Disabling a provider stops gating its embeds — they load exactly as WordPress renders them. Unknown third-party iframes and scripts are always gated by the generic entries.', 'calucon-third-party-embed-gate' ); ?></p>
				<table class="widefat striped" style="max-width: 60rem;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Provider', 'calucon-third-party-embed-gate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Gate', 'calucon-third-party-embed-gate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Privacy-preserving load', 'calucon-third-party-embed-gate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Custom note (optional)', 'calucon-third-party-embed-gate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Custom button text (optional)', 'calucon-third-party-embed-gate' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $this->providers() as $descriptor ) : ?>
						<?php
						$id = isset( $descriptor['id'] ) ? (string) $descriptor['id'] : '';
						if ( '' === $id ) {
							continue;
						}
						$row         = isset( $providers[ $id ] ) ? $providers[ $id ] : array();
						$enabled     = ! isset( $row['enabled'] ) || $row['enabled'];
						$privacy     = ! isset( $row['privacy_variant'] ) || $row['privacy_variant'];
						$has_variant = ! empty( $descriptor['load_host'] ) || ! empty( $descriptor['load_query'] );
						$name_prefix = esc_attr( Options::OPTION . '[providers][' . $id . ']' );
						$label       = isset( $descriptor['label'] ) ? $descriptor['label'] : $id;

						// Accessible names for the row's bare table-cell inputs
						// (WCAG 1.3.1, 4.1.2): the column header alone names
						// nothing in a screen reader's forms mode.
						/* translators: %s: provider label. */
						$aria_gate = sprintf( __( 'Gate %s embeds', 'calucon-third-party-embed-gate' ), $label );
						/* translators: %s: provider label. */
						$aria_privacy = sprintf( __( 'Use the privacy-preserving load for %s', 'calucon-third-party-embed-gate' ), $label );
						/* translators: %s: provider label. */
						$aria_note = sprintf( __( 'Custom note for %s', 'calucon-third-party-embed-gate' ), $label );
						/* translators: %s: provider label. */
						$aria_action = sprintf( __( 'Custom button text for %s', 'calucon-third-party-embed-gate' ), $label );
						?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td>
								<input type="hidden" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>[enabled]" value="0">
								<input type="checkbox" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[enabled]" value="1" aria-label="<?php echo esc_attr( $aria_gate ); ?>" <?php checked( $enabled ); ?>>
							</td>
							<td>
								<?php if ( $has_variant ) : ?>
									<input type="hidden" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[privacy_variant]" value="0">
									<input type="checkbox" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[privacy_variant]" value="1" aria-label="<?php echo esc_attr( $aria_privacy ); ?>" <?php checked( $privacy ); ?>>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><input type="text" class="regular-text" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[note]" aria-label="<?php echo esc_attr( $aria_note ); ?>" value="<?php echo esc_attr( isset( $row['note'] ) ? $row['note'] : '' ); ?>"></td>
							<td><input type="text" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[action]" aria-label="<?php echo esc_attr( $aria_action ); ?>" value="<?php echo esc_attr( isset( $row['action'] ) ? $row['action'] : '' ); ?>"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>

				<div id="cg-tab-detection" class="cg-tab-panel" role="tabpanel" aria-labelledby="cg-tabbtn-detection">
				<h2><?php esc_html_e( 'Detection', 'calucon-third-party-embed-gate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Rules', 'calucon-third-party-embed-gate' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][iframes]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][iframes]" value="1" <?php checked( $detection['iframes'] ); ?>> <?php esc_html_e( 'Gate third-party iframes', 'calucon-third-party-embed-gate' ); ?></label><br>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][scripts]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][scripts]" value="1" <?php checked( $detection['scripts'] ); ?>> <?php esc_html_e( 'Gate third-party scripts in content', 'calucon-third-party-embed-gate' ); ?></label><br>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][images]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][images]" value="1" <?php checked( $detection['images'] ); ?>> <?php esc_html_e( 'Gate third-party images (hotlinked images request the third party with the visitor\'s IP attached; can affect layouts)', 'calucon-third-party-embed-gate' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-always-gate"><?php esc_html_e( 'Always gate these hosts', 'calucon-third-party-embed-gate' ); ?></label></th>
						<td>
							<textarea id="cg-always-gate" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][always_gate]" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $detection['always_gate'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One host per line. These are gated even when they would otherwise count as the site itself — for example a subdomain of your own domain that serves third-party widgets.', 'calucon-third-party-embed-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-own-hosts"><?php esc_html_e( 'Additional own hosts', 'calucon-third-party-embed-gate' ); ?></label></th>
						<td>
							<textarea id="cg-own-hosts" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][own_hosts]" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $detection['own_hosts'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One host per line, e.g. cdn.example.com or *.example.com. These are treated as the site itself and never gated.', 'calucon-third-party-embed-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-never-gate"><?php esc_html_e( 'Never gate these hosts', 'calucon-third-party-embed-gate' ); ?></label></th>
						<td>
							<textarea id="cg-never-gate" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][never_gate]" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $detection['never_gate'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Embeds from these hosts load without a placeholder. Use only for third parties you have covered elsewhere — this plugin then no longer prevents their requests.', 'calucon-third-party-embed-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Page builders', 'calucon-third-party-embed-gate' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][output_buffer]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][output_buffer]" value="1" <?php checked( $detection['output_buffer'] ); ?>> <?php esc_html_e( 'Gate the whole page output (for Elementor, Divi, WPBakery, Bricks)', 'calucon-third-party-embed-gate' ); ?></label>
							<p class="description"><?php esc_html_e( 'Only enable this if embeds from a page builder are not being gated. It buffers the entire page, which can conflict with other buffering or streaming plugins. Any error inside the buffer returns the page unmodified.', 'calucon-third-party-embed-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Host matching', 'calucon-third-party-embed-gate' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][www_equivalence]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][www_equivalence]" value="1" <?php checked( $detection['www_equivalence'] ); ?>> <?php esc_html_e( 'Treat www.example.com and example.com as the same site', 'calucon-third-party-embed-gate' ); ?></label>
						</td>
					</tr>
				</table>
				</div>

				<div id="cg-tab-appearance" class="cg-tab-panel" role="tabpanel" aria-labelledby="cg-tabbtn-appearance">
				<h2><?php esc_html_e( 'Appearance', 'calucon-third-party-embed-gate' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Style the placeholder panel without writing any CSS: pick a style, pick colours, and watch the preview below update as you go. The readability check tells you immediately if a colour combination would be hard to read.', 'calucon-third-party-embed-gate' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cg-preset"><?php esc_html_e( 'Panel style', 'calucon-third-party-embed-gate' ); ?></label></th>
						<td>
							<select id="cg-preset" name="<?php echo esc_attr( Options::OPTION ); ?>[appearance][preset]">
								<option value="default" <?php selected( $options['appearance']['preset'], 'default' ); ?>><?php esc_html_e( 'Default — filled panel', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="minimal" <?php selected( $options['appearance']['preset'], 'minimal' ); ?>><?php esc_html_e( 'Minimal — transparent with a border', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="card" <?php selected( $options['appearance']['preset'], 'card' ); ?>><?php esc_html_e( 'Card — border, rounded corners, shadow', 'calucon-third-party-embed-gate' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-corners"><?php esc_html_e( 'Corners', 'calucon-third-party-embed-gate' ); ?></label></th>
						<td>
							<select id="cg-corners" name="<?php echo esc_attr( Options::OPTION ); ?>[appearance][corners]">
								<option value="" <?php selected( $options['appearance']['corners'], '' ); ?>><?php esc_html_e( 'Default — slightly rounded', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="square" <?php selected( $options['appearance']['corners'], 'square' ); ?>><?php esc_html_e( 'Square', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="rounded" <?php selected( $options['appearance']['corners'], 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="pill" <?php selected( $options['appearance']['corners'], 'pill' ); ?>><?php esc_html_e( 'Rounded, with a pill-shaped button', 'calucon-third-party-embed-gate' ); ?></option>
							</select>
						</td>
					</tr>
					<?php
					$color_fields = array(
						'bg'        => __( 'Panel background', 'calucon-third-party-embed-gate' ),
						'fg'        => __( 'Panel text', 'calucon-third-party-embed-gate' ),
						'accent'    => __( 'Button background', 'calucon-third-party-embed-gate' ),
						'accent_fg' => __( 'Button text', 'calucon-third-party-embed-gate' ),
					);
					foreach ( $color_fields as $color_key => $color_label ) :
						$color_id = 'cg-color-' . str_replace( '_', '-', $color_key );
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $color_id ); ?>"><?php echo esc_html( $color_label ); ?></label></th>
							<td>
								<input type="text" id="<?php echo esc_attr( $color_id ); ?>" class="cg-color-field" data-cg-color="<?php echo esc_attr( $color_key ); ?>" name="<?php echo esc_attr( Options::OPTION . '[appearance][' . $color_key . ']' ); ?>" value="<?php echo esc_attr( $options['appearance'][ $color_key ] ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="description"><?php esc_html_e( 'A cleared colour inherits your theme\'s palette — that is the default, and usually the best choice. The preview cannot use your theme\'s palette here in the admin, so with cleared colours it shows the plugin\'s built-in look; on your site the panel follows the theme.', 'calucon-third-party-embed-gate' ); ?></p>

				<?php $this->render_preview(); ?>
				</div>

				<div id="cg-tab-consent" class="cg-tab-panel" role="tabpanel" aria-labelledby="cg-tabbtn-consent">
				<h2><?php esc_html_e( 'Consent memory', 'calucon-third-party-embed-gate' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Off by default: consent applies to the one embed clicked and is stored nowhere. When enabled, the choice is stored in the visitor\'s browser only — after their first click, never before — and a withdrawal control becomes available via the [calucon_embed_gate_withdraw] shortcode for your privacy policy page.', 'calucon-third-party-embed-gate' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cg-memory"><?php esc_html_e( 'Remember consent', 'calucon-third-party-embed-gate' ); ?></label></th>
						<td>
							<select id="cg-memory" name="<?php echo esc_attr( Options::OPTION ); ?>[consent][memory]">
								<option value="off" <?php selected( $options['consent']['memory'], 'off' ); ?>><?php esc_html_e( 'No (default) — ask on every page view', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="session" <?php selected( $options['consent']['memory'], 'session' ); ?>><?php esc_html_e( 'For this browser session', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="persistent" <?php selected( $options['consent']['memory'], 'persistent' ); ?>><?php esc_html_e( 'Persistently, with an expiry', 'calucon-third-party-embed-gate' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-scope"><?php esc_html_e( 'Scope', 'calucon-third-party-embed-gate' ); ?></label></th>
						<td>
							<select id="cg-scope" name="<?php echo esc_attr( Options::OPTION ); ?>[consent][scope]">
								<option value="embed" <?php selected( $options['consent']['scope'], 'embed' ); ?>><?php esc_html_e( 'This embed only', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="provider" <?php selected( $options['consent']['scope'], 'provider' ); ?>><?php esc_html_e( 'All embeds of the same provider', 'calucon-third-party-embed-gate' ); ?></option>
								<option value="all" <?php selected( $options['consent']['scope'], 'all' ); ?>><?php esc_html_e( 'All embeds', 'calucon-third-party-embed-gate' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-duration"><?php esc_html_e( 'Persistent lifetime (days)', 'calucon-third-party-embed-gate' ); ?></label></th>
						<td><input type="number" id="cg-duration" min="1" max="730" name="<?php echo esc_attr( Options::OPTION ); ?>[consent][duration_days]" value="<?php echo esc_attr( (string) $options['consent']['duration_days'] ); ?>"></td>
					</tr>
				</table>

				<?php $this->render_cmp_bridge( $options ); ?>

				</div>

				<?php submit_button(); ?>
			</form>

			<?php
			// Read-only diagnostics and generated snippets: admin-tabs.js hides
			// the form's Save button while this panel is active (data-cg-readonly).
			?>
			<div id="cg-tab-status" class="cg-tab-panel" role="tabpanel" aria-labelledby="cg-tabbtn-status" data-cg-readonly="1">
			<h2><?php esc_html_e( 'Content-Security-Policy snippet', 'calucon-third-party-embed-gate' ); ?></h2>
			<p class="description"><?php esc_html_e( 'If your site sends a Content-Security-Policy, it needs to allow the enabled providers\' hosts so embeds can load after consent. These hosts are not contacted until the visitor clicks — the CSP entry is permission, not traffic.', 'calucon-third-party-embed-gate' ); ?></p>
			<textarea readonly rows="4" class="large-text code" aria-label="<?php echo esc_attr( __( 'Content-Security-Policy snippet', 'calucon-third-party-embed-gate' ) ); ?>"><?php echo esc_textarea( Csp::snippet( $this->providers() ) ); ?></textarea>

			<?php $this->render_compatibility(); ?>
			<?php $this->render_status(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Live preview of the placeholder panel, driven by admin-appearance.js:
	 * the sample is real renderer output styled by the real front-end
	 * stylesheet, so what the owner sees here is what visitors get. Inert by
	 * design — gate.js is not loaded in the admin and the script suppresses
	 * link navigation inside the stage.
	 *
	 * @return void
	 */
	private function render_preview(): void {
		if ( null === $this->preview_source ) {
			return;
		}
		$sample = (string) call_user_func( $this->preview_source );
		if ( '' === $sample ) {
			return;
		}
		?>
		<h3><?php esc_html_e( 'Preview', 'calucon-third-party-embed-gate' ); ?></h3>
		<div id="cg-preview-stage" class="cg-preview-stage">
			<?php echo $sample; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- placeholder HTML escaped by the renderer, same output as the front end. ?>
		</div>
		<p>
			<label>
				<input type="checkbox" id="cg-preview-dark">
				<?php esc_html_e( 'Preview on a dark page background', 'calucon-third-party-embed-gate' ); ?>
			</label>
		</p>
		<p id="cg-contrast-report" class="cg-contrast-report" role="status" aria-live="polite"></p>
		<?php
	}

	/**
	 * The §6.4 consent-platform bridge settings, inside the Consent tab.
	 *
	 * The bridge is offered only for platforms on the tested list; the list
	 * itself is printed so the promise is explicit — an untested platform
	 * is simply not bridged (fail closed), never half-bridged.
	 *
	 * @param array $options Sanitised option tree.
	 * @return void
	 */
	private function render_cmp_bridge( array $options ): void {
		$detected = Detector::detected();
		$labels   = array();
		foreach ( Detector::bridgeable() as $row ) {
			$labels[] = $row['label'];
		}
		?>
		<h2><?php esc_html_e( 'Consent platform bridge', 'calucon-third-party-embed-gate' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'If a consent platform (cookie banner) runs on this site, Calucon Third-Party Embed Gate can honour its decision: once the platform reports consent for the embeds\' category, gated embeds load without a second click — and a withdrawal there re-gates them. The bridge only reads the platform\'s state, stores nothing itself, and works only with platforms it was tested against; with any other platform, and whenever the platform gives no answer, gating stands unchanged (fail closed).', 'calucon-third-party-embed-gate' ); ?>
		</p>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: comma-separated list of consent platforms. */
					__( 'Tested and interoperable: %s.', 'calucon-third-party-embed-gate' ),
					implode( ', ', $labels )
				)
			);
			?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Bridge', 'calucon-third-party-embed-gate' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[cmp][bridge]" value="0">
					<label for="cg-cmp-bridge">
						<input type="checkbox" id="cg-cmp-bridge" name="<?php echo esc_attr( Options::OPTION ); ?>[cmp][bridge]" value="1" <?php checked( $options['cmp']['bridge'] ); ?>>
						<?php esc_html_e( 'Load embeds automatically when the detected consent platform reports consent for them', 'calucon-third-party-embed-gate' ); ?>
					</label>
					<p class="description">
						<?php
						if ( array() === $detected ) {
							esc_html_e( 'No tested consent platform is currently detected. The setting can stay enabled; it takes effect as soon as one is installed.', 'calucon-third-party-embed-gate' );
						} else {
							$names = array();
							foreach ( $detected as $cmp ) {
								$names[] = $cmp['label'];
							}
							echo esc_html(
								sprintf(
									/* translators: %s: comma-separated list of detected consent platforms. */
									__( 'Detected now: %s.', 'calucon-third-party-embed-gate' ),
									implode( ', ', $names )
								)
							);
						}
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cg-cmp-borlabs-group"><?php esc_html_e( 'Borlabs Cookie service group', 'calucon-third-party-embed-gate' ); ?></label></th>
				<td>
					<input type="text" id="cg-cmp-borlabs-group" name="<?php echo esc_attr( Options::OPTION ); ?>[cmp][borlabs_group]" value="<?php echo esc_attr( $options['cmp']['borlabs_group'] ); ?>" class="regular-text" pattern="[a-z0-9_-]{1,64}">
					<p class="description"><?php esc_html_e( 'Only used with Borlabs Cookie, whose consent groups are defined per site: the ID of the group that covers embedded content. The default installation calls it "external-media".', 'calucon-third-party-embed-gate' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'IAB TCF (experimental)', 'calucon-third-party-embed-gate' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[cmp][tcf]" value="0">
					<label for="cg-cmp-tcf">
						<input type="checkbox" id="cg-cmp-tcf" name="<?php echo esc_attr( Options::OPTION ); ?>[cmp][tcf]" value="1" <?php checked( $options['cmp']['tcf'] ); ?>>
						<?php esc_html_e( 'Also honour an IAB TCF v2.2 signal (sites running an ad-industry consent framework)', 'calucon-third-party-embed-gate' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Grants require both the storage purpose and the provider\'s registered vendor consent; providers without a Global Vendor List entry always keep the click. Leave this off unless your site serves programmatic advertising.', 'calucon-third-party-embed-gate' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Compatibility (§7.1): the detected CMP, cache plugin and page builder,
	 * and what the plugin decided to do about each.
	 *
	 * @return void
	 */
	private function render_compatibility(): void {
		$found    = Compatibility::detect();
		$options  = Options::sanitize( get_option( Options::OPTION, Options::defaults() ) );
		$messages = array(
			'cache'   => __( 'Detected. Its page cache is flushed automatically when Calucon Third-Party Embed Gate settings change; after activating or deactivating Calucon Third-Party Embed Gate itself, clear it once by hand if pages look stale.', 'calucon-third-party-embed-gate' ),
			'builder' => $options['detection']['output_buffer']
				? __( 'Detected. Whole-page gating is enabled, so this builder\'s embeds are covered.', 'calucon-third-party-embed-gate' )
				: __( 'Detected. Page builders render outside the content filters — if its embeds are not being gated, enable "Gate the whole page output" under Detection.', 'calucon-third-party-embed-gate' ),
		);
		// CMP rows (§6.4) depend on the row itself: tested platforms can be
		// bridged; anything else keeps the fail-closed default.
		$cmp_messages = array(
			'active'    => __( 'Detected, bridge active: when this platform reports consent for the embeds\' category, gated embeds load without a second click, and a withdrawal re-gates them. If the platform does not answer, gating stands (fail closed). Prefer its own blocker for a provider? Disable that provider under Providers and Calucon Third-Party Embed Gate steps aside for it.', 'calucon-third-party-embed-gate' ),
			'available' => __( 'Detected and tested for interoperation. Gating currently ignores its choices — the fail-closed default. Enable the consent platform bridge under Consent to load embeds automatically once this platform reports consent for them.', 'calucon-third-party-embed-gate' ),
			'untested'  => __( 'Detected. Calucon Third-Party Embed Gate has no tested bridge to this consent platform and keeps gating regardless of its choices — the fail-closed default. Nothing loads before the embed-level click.', 'calucon-third-party-embed-gate' ),
		);
		?>
		<h2 id="cg-compatibility"><?php esc_html_e( 'Compatibility', 'calucon-third-party-embed-gate' ); ?></h2>
		<?php if ( array() === $found ) : ?>
			<p><?php esc_html_e( 'No cache plugin, consent platform or page builder detected.', 'calucon-third-party-embed-gate' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width: 60rem;">
				<thead><tr><th scope="col"><?php esc_html_e( 'Detected', 'calucon-third-party-embed-gate' ); ?></th><th scope="col"><?php esc_html_e( 'What Calucon Third-Party Embed Gate does', 'calucon-third-party-embed-gate' ); ?></th></tr></thead>
				<tbody>
				<?php
				foreach ( $found as $row ) :
					if ( 'cmp' === $row['kind'] ) {
						if ( empty( $row['tested'] ) ) {
							$message = $cmp_messages['untested'];
						} else {
							$message = $options['cmp']['bridge'] ? $cmp_messages['active'] : $cmp_messages['available'];
						}
					} else {
						$message = $messages[ $row['kind'] ];
					}
					?>
					<tr><td><?php echo esc_html( $row['name'] ); ?></td><td><?php echo esc_html( $message ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php
		$theme_findings = Compatibility::theme_asset_findings();
		if ( array() !== $theme_findings ) :
			?>
			<h3><?php esc_html_e( 'Third-party assets in your theme', 'calucon-third-party-embed-gate' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Your theme references these third-party asset hosts (found by reading its files — nothing was fetched). Fonts and CDN assets load on every page view without consent, outside what an embed gate can cover. Consider serving them locally; your theme or a localisation plugin can usually do this.', 'calucon-third-party-embed-gate' ); ?></p>
			<ul>
				<?php foreach ( $theme_findings as $finding ) : ?>
					<li><code><?php echo esc_html( $finding['file'] ); ?></code> — <?php echo esc_html( implode( ', ', $finding['hosts'] ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Status (§7.1): a read-only scan of recent content — which third-party
	 * hosts appear and whether each is currently gated. Runs only on demand:
	 * rendering 50 posts through the content filters is not free.
	 *
	 * @return void
	 */
	private function render_status(): void {
		if ( null === $this->scanner_source ) {
			return;
		}
		?>
		<h2 id="cg-status"><?php esc_html_e( 'Status', 'calucon-third-party-embed-gate' ); ?></h2>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only scan, no state changes; capability-gated by the page.
		if ( ! isset( $_GET['cg-scan'] ) ) {
			?>
			<p>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'cg-scan', '1' ) . '#cg-status' ); ?>"><?php esc_html_e( 'Scan recent content', 'calucon-third-party-embed-gate' ); ?></a>
				<span class="description"><?php esc_html_e( 'Renders your latest posts and pages in memory and reports every embed found and whether it is gated. Read-only; no outbound requests.', 'calucon-third-party-embed-gate' ); ?></span>
			</p>
			<?php
			return;
		}

		$scanner = call_user_func( $this->scanner_source );
		$posts   = get_posts(
			array(
				'post_type'        => array( 'post', 'page' ),
				'post_status'      => 'publish',
				'numberposts'      => 50,
				'suppress_filters' => false,
			)
		);

		$status_labels = array(
			\CaluconEmbedGate\Support\ContentScan::GATED => __( 'Gated', 'calucon-third-party-embed-gate' ),
			\CaluconEmbedGate\Support\ContentScan::OWN_HOST => __( 'Own host — not gated', 'calucon-third-party-embed-gate' ),
			\CaluconEmbedGate\Support\ContentScan::NO_USABLE_URL => __( 'No usable URL — passes through', 'calucon-third-party-embed-gate' ),
			\CaluconEmbedGate\Support\ContentScan::RULE_DISABLED => __( 'NOT gated — its detection rule is disabled', 'calucon-third-party-embed-gate' ),
			\CaluconEmbedGate\Support\ContentScan::PROVIDER_DISABLED => __( 'NOT gated — provider disabled in the table above', 'calucon-third-party-embed-gate' ),
		);

		$scanned = array();
		foreach ( $posts as $post ) {
			$rendered  = (string) apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- deliberately rendering through core's own content pipeline so embeds appear as they would on the front end.
			$scanned[] = array(
				'source' => get_the_title( $post ),
				'rows'   => $scanner->scan( $rendered ),
			);
		}
		$rows = \CaluconEmbedGate\Support\ContentScan::aggregate( $scanned );
		?>
		<p class="description">
			<?php
			printf(
				/* translators: %d: number of posts scanned. */
				esc_html__( 'Scanned the %d most recent published posts and pages. Widgets, template parts and builder-rendered layouts are not part of this scan.', 'calucon-third-party-embed-gate' ),
				count( $posts )
			);
			?>
		</p>
		<?php if ( array() === $rows ) : ?>
			<p><?php esc_html_e( 'No third-party embeds found in the scanned content.', 'calucon-third-party-embed-gate' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width: 60rem;">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Host', 'calucon-third-party-embed-gate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'calucon-third-party-embed-gate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Count', 'calucon-third-party-embed-gate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'calucon-third-party-embed-gate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'First seen in', 'calucon-third-party-embed-gate' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( '' !== $row['host'] ? $row['host'] : '—' ); ?></td>
						<td><code><?php echo esc_html( $row['tag'] ); ?></code><?php echo '' !== $row['label'] ? ' ' . esc_html( '(' . $row['label'] . ')' ) : ''; ?></td>
						<td><?php echo esc_html( (string) $row['count'] ); ?></td>
						<td><?php echo esc_html( isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : $row['status'] ); ?></td>
						<td><?php echo esc_html( $row['first_seen'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;
	}
}
