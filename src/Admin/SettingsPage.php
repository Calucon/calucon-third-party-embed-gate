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

	/** @var array[] Normalised provider descriptors for the table. */
	private array $providers;

	/**
	 * @param array[] $providers Provider descriptors (builtins + filtered).
	 */
	public function __construct( array $providers ) {
		$this->providers = $providers;
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
							<th><?php esc_html_e( 'Provider', 'consent-gate' ); ?></th>
							<th><?php esc_html_e( 'Gate', 'consent-gate' ); ?></th>
							<th><?php esc_html_e( 'Privacy-preserving load', 'consent-gate' ); ?></th>
							<th><?php esc_html_e( 'Custom note (optional)', 'consent-gate' ); ?></th>
							<th><?php esc_html_e( 'Custom button text (optional)', 'consent-gate' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $this->providers as $descriptor ) : ?>
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
						?>
						<tr>
							<td><?php echo esc_html( isset( $descriptor['label'] ) ? $descriptor['label'] : $id ); ?></td>
							<td>
								<input type="hidden" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>[enabled]" value="0">
								<input type="checkbox" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[enabled]" value="1" <?php checked( $enabled ); ?>>
							</td>
							<td>
								<?php if ( $has_variant ) : ?>
									<input type="hidden" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[privacy_variant]" value="0">
									<input type="checkbox" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[privacy_variant]" value="1" <?php checked( $privacy ); ?>>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td><input type="text" class="regular-text" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[note]" value="<?php echo esc_attr( isset( $row['note'] ) ? $row['note'] : '' ); ?>"></td>
							<td><input type="text" name="<?php echo $name_prefix; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>[action]" value="<?php echo esc_attr( isset( $row['action'] ) ? $row['action'] : '' ); ?>"></td>
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
							<label><input type="checkbox" name="<?php echo esc_attr( Options::OPTION ); ?>[detection][scripts]" value="1" <?php checked( $detection['scripts'] ); ?>> <?php esc_html_e( 'Gate third-party scripts in content', 'consent-gate' ); ?></label>
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
			<textarea readonly rows="4" class="large-text code"><?php echo esc_textarea( Csp::snippet( $this->providers ) ); ?></textarea>
		</div>
		<?php
	}
}
