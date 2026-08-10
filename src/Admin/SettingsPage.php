<?php
/**
 * Settings screen (PLAN.md §7.1, M3 subset: Providers + Detection).
 *
 * Admin/ is allowed to use WordPress globals (PLAN.md §2.2). Everything
 * user-submitted goes through Options::sanitize(); everything printed goes
 * through esc_*(); the form is nonce-protected by the Settings API.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Admin;

use ConsentGate\Support\Csp;
use ConsentGate\Support\Options;

/**
 * Settings > Consent Gate.
 */
final class SettingsPage {

	/** @var callable Returns the provider descriptors; resolved lazily so
	 *                providers registered by the theme's functions.php (which
	 *                loads after plugins_loaded) appear in the table and the
	 *                CSP snippet. */
	private $providers_source;

	/** @var callable|null Returns the ContentScan behind the Status screen. */
	private $scanner_source;

	/**
	 * @param callable      $providers_source fn(): array[] — builtins + filtered.
	 * @param callable|null $scanner_source   fn(): \ConsentGate\Support\ContentScan.
	 */
	public function __construct( callable $providers_source, ?callable $scanner_source = null ) {
		$this->providers_source = $providers_source;
		$this->scanner_source   = $scanner_source;
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
	}

	/**
	 * @return void
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'Consent Gate', 'consent-gate' ),
			__( 'Consent Gate', 'consent-gate' ),
			'manage_options',
			'consent-gate',
			array( $this, 'render' )
		);
	}

	/**
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			'consent_gate',
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
			<h1><?php esc_html_e( 'Consent Gate', 'consent-gate' ); ?></h1>
			<p><?php esc_html_e( 'Third-party embeds are replaced with a placeholder until the visitor clicks to load them. Nothing is contacted, and nothing is stored, before that click.', 'consent-gate' ); ?></p>

			<form action="options.php" method="post">
				<?php settings_fields( 'consent_gate' ); ?>

				<h2><?php esc_html_e( 'Providers', 'consent-gate' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Disabling a provider stops gating its embeds — they load exactly as WordPress renders them. Unknown third-party iframes and scripts are always gated by the generic entries.', 'consent-gate' ); ?></p>
				<table class="widefat striped" style="max-width: 60rem;">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Provider', 'consent-gate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Gate', 'consent-gate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Privacy-preserving load', 'consent-gate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Custom note (optional)', 'consent-gate' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Custom button text (optional)', 'consent-gate' ); ?></th>
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
						$aria_gate = sprintf( __( 'Gate %s embeds', 'consent-gate' ), $label );
						/* translators: %s: provider label. */
						$aria_privacy = sprintf( __( 'Use the privacy-preserving load for %s', 'consent-gate' ), $label );
						/* translators: %s: provider label. */
						$aria_note = sprintf( __( 'Custom note for %s', 'consent-gate' ), $label );
						/* translators: %s: provider label. */
						$aria_action = sprintf( __( 'Custom button text for %s', 'consent-gate' ), $label );
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

				<h2><?php esc_html_e( 'Detection', 'consent-gate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Rules', 'consent-gate' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][iframes]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][iframes]" value="1" <?php checked( $detection['iframes'] ); ?>> <?php esc_html_e( 'Gate third-party iframes', 'consent-gate' ); ?></label><br>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][scripts]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][scripts]" value="1" <?php checked( $detection['scripts'] ); ?>> <?php esc_html_e( 'Gate third-party scripts in content', 'consent-gate' ); ?></label><br>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][images]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][images]" value="1" <?php checked( $detection['images'] ); ?>> <?php esc_html_e( 'Gate third-party images (hotlinked images request the third party with the visitor\'s IP attached; can affect layouts)', 'consent-gate' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-always-gate"><?php esc_html_e( 'Always gate these hosts', 'consent-gate' ); ?></label></th>
						<td>
							<textarea id="cg-always-gate" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][always_gate]" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $detection['always_gate'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One host per line. These are gated even when they would otherwise count as the site itself — for example a subdomain of your own domain that serves third-party widgets.', 'consent-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-own-hosts"><?php esc_html_e( 'Additional own hosts', 'consent-gate' ); ?></label></th>
						<td>
							<textarea id="cg-own-hosts" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][own_hosts]" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $detection['own_hosts'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One host per line, e.g. cdn.example.com or *.example.com. These are treated as the site itself and never gated.', 'consent-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-never-gate"><?php esc_html_e( 'Never gate these hosts', 'consent-gate' ); ?></label></th>
						<td>
							<textarea id="cg-never-gate" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][never_gate]" rows="3" class="large-text code"><?php echo esc_textarea( implode( "\n", $detection['never_gate'] ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Embeds from these hosts load without a placeholder. Use only for third parties you have covered elsewhere — this plugin then no longer prevents their requests.', 'consent-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Page builders', 'consent-gate' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][output_buffer]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][output_buffer]" value="1" <?php checked( $detection['output_buffer'] ); ?>> <?php esc_html_e( 'Gate the whole page output (for Elementor, Divi, WPBakery, Bricks)', 'consent-gate' ); ?></label>
							<p class="description"><?php esc_html_e( 'Only enable this if embeds from a page builder are not being gated. It buffers the entire page, which can conflict with other buffering or streaming plugins. Any error inside the buffer returns the page unmodified.', 'consent-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Host matching', 'consent-gate' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][www_equivalence]" value="0">
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][www_equivalence]" value="1" <?php checked( $detection['www_equivalence'] ); ?>> <?php esc_html_e( 'Treat www.example.com and example.com as the same site', 'consent-gate' ); ?></label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Appearance', 'consent-gate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cg-preset"><?php esc_html_e( 'Preset', 'consent-gate' ); ?></label></th>
						<td>
							<select id="cg-preset" name="<?php echo esc_attr( Options::OPTION ); ?>[appearance][preset]">
								<option value="default" <?php selected( $options['appearance']['preset'], 'default' ); ?>><?php esc_html_e( 'Default — filled panel', 'consent-gate' ); ?></option>
								<option value="minimal" <?php selected( $options['appearance']['preset'], 'minimal' ); ?>><?php esc_html_e( 'Minimal — transparent with a border', 'consent-gate' ); ?></option>
								<option value="card" <?php selected( $options['appearance']['preset'], 'card' ); ?>><?php esc_html_e( 'Card — border, rounded corners, shadow', 'consent-gate' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Colours', 'consent-gate' ); ?></th>
						<td>
							<?php
							$color_fields = array(
								'bg'        => __( 'Panel background', 'consent-gate' ),
								'fg'        => __( 'Panel text', 'consent-gate' ),
								'accent'    => __( 'Button background', 'consent-gate' ),
								'accent_fg' => __( 'Button text', 'consent-gate' ),
							);
							foreach ( $color_fields as $color_key => $color_label ) :
								?>
								<label style="display:inline-block;margin:0 1.5em 0.5em 0;">
									<?php echo esc_html( $color_label ); ?><br>
									<input type="text" class="small-text code" placeholder="#rrggbb" name="<?php echo esc_attr( Options::OPTION . '[appearance][' . $color_key . ']' ); ?>" value="<?php echo esc_attr( $options['appearance'][ $color_key ] ); ?>">
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Hex colours (#rrggbb); leave empty to inherit the theme\'s palette. If you set the button background, set the button text too and keep them at a 4.5:1 contrast ratio — the defaults are chosen to meet WCAG and custom colours are your responsibility.', 'consent-gate' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Consent memory', 'consent-gate' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Off by default: consent applies to the one embed clicked and is stored nowhere. When enabled, the choice is stored in the visitor\'s browser only — after their first click, never before — and a withdrawal control becomes available via the [consent_gate_withdraw] shortcode for your privacy policy page.', 'consent-gate' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cg-memory"><?php esc_html_e( 'Remember consent', 'consent-gate' ); ?></label></th>
						<td>
							<select id="cg-memory" name="<?php echo esc_attr( Options::OPTION ); ?>[consent][memory]">
								<option value="off" <?php selected( $options['consent']['memory'], 'off' ); ?>><?php esc_html_e( 'No (default) — ask on every page view', 'consent-gate' ); ?></option>
								<option value="session" <?php selected( $options['consent']['memory'], 'session' ); ?>><?php esc_html_e( 'For this browser session', 'consent-gate' ); ?></option>
								<option value="persistent" <?php selected( $options['consent']['memory'], 'persistent' ); ?>><?php esc_html_e( 'Persistently, with an expiry', 'consent-gate' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-scope"><?php esc_html_e( 'Scope', 'consent-gate' ); ?></label></th>
						<td>
							<select id="cg-scope" name="<?php echo esc_attr( Options::OPTION ); ?>[consent][scope]">
								<option value="embed" <?php selected( $options['consent']['scope'], 'embed' ); ?>><?php esc_html_e( 'This embed only', 'consent-gate' ); ?></option>
								<option value="provider" <?php selected( $options['consent']['scope'], 'provider' ); ?>><?php esc_html_e( 'All embeds of the same provider', 'consent-gate' ); ?></option>
								<option value="all" <?php selected( $options['consent']['scope'], 'all' ); ?>><?php esc_html_e( 'All embeds', 'consent-gate' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cg-duration"><?php esc_html_e( 'Persistent lifetime (days)', 'consent-gate' ); ?></label></th>
						<td><input type="number" id="cg-duration" min="1" max="730" name="<?php echo esc_attr( Options::OPTION ); ?>[consent][duration_days]" value="<?php echo esc_attr( (string) $options['consent']['duration_days'] ); ?>"></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Content-Security-Policy snippet', 'consent-gate' ); ?></h2>
			<p class="description"><?php esc_html_e( 'If your site sends a Content-Security-Policy, it needs to allow the enabled providers\' hosts so embeds can load after consent. These hosts are not contacted until the visitor clicks — the CSP entry is permission, not traffic.', 'consent-gate' ); ?></p>
			<textarea readonly rows="4" class="large-text code" aria-label="<?php echo esc_attr( __( 'Content-Security-Policy snippet', 'consent-gate' ) ); ?>"><?php echo esc_textarea( Csp::snippet( $this->providers() ) ); ?></textarea>

			<?php $this->render_disclosure(); ?>
			<?php $this->render_compatibility(); ?>
			<?php $this->render_status(); ?>
		</div>
		<?php
	}

	/**
	 * Privacy-policy disclosure draft (PLAN.md §14): assembled from the
	 * provider descriptors' controller/privacy_url data. A DRAFT the owner
	 * must review — the plugin cannot know the site's processing purposes
	 * and never claims compliance (invariant 10).
	 *
	 * @return void
	 */
	private function render_disclosure(): void {
		$draft = \ConsentGate\Support\Disclosure::draft(
			$this->providers(),
			static function ( string $text ): string {
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- bridged strings are extracted where they are defined.
				return __( $text, 'consent-gate' );
			}
		);
		if ( '' === $draft ) {
			return;
		}
		?>
		<h2 id="cg-disclosure"><?php esc_html_e( 'Privacy policy disclosure (draft)', 'consent-gate' ); ?></h2>
		<p class="description"><?php esc_html_e( 'A starting point for the section of your privacy policy that names your embed providers, generated from the enabled providers above. Review it, adapt it to your site and your language, and remove providers you do not embed from — your privacy policy remains your responsibility, and this text is generated data, not legal advice.', 'consent-gate' ); ?></p>
		<textarea readonly rows="14" class="large-text" aria-label="<?php echo esc_attr( __( 'Privacy policy disclosure draft', 'consent-gate' ) ); ?>"><?php echo esc_textarea( $draft ); ?></textarea>
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
			'cache'   => __( 'Detected. Its page cache is flushed automatically when Consent Gate settings change; after activating or deactivating Consent Gate itself, clear it once by hand if pages look stale.', 'consent-gate' ),
			'cmp'     => __( 'Detected. Consent Gate has no bridge to this consent platform yet and keeps gating regardless of its choices — the fail-closed default. Nothing loads before the embed-level click.', 'consent-gate' ),
			'builder' => $options['detection']['output_buffer']
				? __( 'Detected. Whole-page gating is enabled, so this builder\'s embeds are covered.', 'consent-gate' )
				: __( 'Detected. Page builders render outside the content filters — if its embeds are not being gated, enable "Gate the whole page output" under Detection.', 'consent-gate' ),
		);
		?>
		<h2 id="cg-compatibility"><?php esc_html_e( 'Compatibility', 'consent-gate' ); ?></h2>
		<?php if ( array() === $found ) : ?>
			<p><?php esc_html_e( 'No cache plugin, consent platform or page builder detected.', 'consent-gate' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width: 60rem;">
				<thead><tr><th scope="col"><?php esc_html_e( 'Detected', 'consent-gate' ); ?></th><th scope="col"><?php esc_html_e( 'What Consent Gate does', 'consent-gate' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $found as $row ) : ?>
					<tr><td><?php echo esc_html( $row['name'] ); ?></td><td><?php echo esc_html( $messages[ $row['kind'] ] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php
		$theme_findings = Compatibility::theme_asset_findings();
		if ( array() !== $theme_findings ) :
			?>
			<h3><?php esc_html_e( 'Third-party assets in your theme', 'consent-gate' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Your theme references these third-party asset hosts (found by reading its files — nothing was fetched). Fonts and CDN assets load on every page view without consent, outside what an embed gate can cover. Consider serving them locally; your theme or a localisation plugin can usually do this.', 'consent-gate' ); ?></p>
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
		<h2 id="cg-status"><?php esc_html_e( 'Status', 'consent-gate' ); ?></h2>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only scan, no state changes; capability-gated by the page.
		if ( ! isset( $_GET['cg-scan'] ) ) {
			?>
			<p>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'cg-scan', '1' ) . '#cg-status' ); ?>"><?php esc_html_e( 'Scan recent content', 'consent-gate' ); ?></a>
				<span class="description"><?php esc_html_e( 'Renders your latest posts and pages in memory and reports every embed found and whether it is gated. Read-only; no outbound requests.', 'consent-gate' ); ?></span>
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
			\ConsentGate\Support\ContentScan::GATED    => __( 'Gated', 'consent-gate' ),
			\ConsentGate\Support\ContentScan::OWN_HOST => __( 'Own host — not gated', 'consent-gate' ),
			\ConsentGate\Support\ContentScan::NO_USABLE_URL => __( 'No usable URL — passes through', 'consent-gate' ),
			\ConsentGate\Support\ContentScan::RULE_DISABLED => __( 'NOT gated — its detection rule is disabled', 'consent-gate' ),
			\ConsentGate\Support\ContentScan::PROVIDER_DISABLED => __( 'NOT gated — provider disabled in the table above', 'consent-gate' ),
		);

		$rows = array();
		foreach ( $posts as $post ) {
			$rendered = (string) apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- deliberately rendering through core's own content pipeline so embeds appear as they would on the front end.
			foreach ( $scanner->scan( $rendered ) as $row ) {
				$key = $row['tag'] . '|' . $row['host'] . '|' . $row['status'];
				if ( ! isset( $rows[ $key ] ) ) {
					$rows[ $key ]          = $row;
					$rows[ $key ]['count'] = 0;
					$rows[ $key ]['where'] = get_the_title( $post );
				}
				++$rows[ $key ]['count'];
			}
		}
		?>
		<p class="description">
			<?php
			printf(
				/* translators: %d: number of posts scanned. */
				esc_html__( 'Scanned the %d most recent published posts and pages. Widgets, template parts and builder-rendered layouts are not part of this scan.', 'consent-gate' ),
				count( $posts )
			);
			?>
		</p>
		<?php if ( array() === $rows ) : ?>
			<p><?php esc_html_e( 'No third-party embeds found in the scanned content.', 'consent-gate' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width: 60rem;">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Host', 'consent-gate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'consent-gate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Count', 'consent-gate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'consent-gate' ); ?></th>
					<th scope="col"><?php esc_html_e( 'First seen in', 'consent-gate' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( '' !== $row['host'] ? $row['host'] : '—' ); ?></td>
						<td><code><?php echo esc_html( $row['tag'] ); ?></code><?php echo '' !== $row['label'] ? ' ' . esc_html( '(' . $row['label'] . ')' ) : ''; ?></td>
						<td><?php echo esc_html( (string) $row['count'] ); ?></td>
						<td><?php echo esc_html( isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : $row['status'] ); ?></td>
						<td><?php echo esc_html( $row['where'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;
	}
}
