<?php
/**
 * Seeds what the field-validation suite needs beyond tests/wp/seed.php:
 * a probe the specs use to prove the plugins under test are really active,
 * and per-group content. Executed inside WordPress by tests/wp/field-setup.sh:
 *
 *   wp eval-file …/tests/wp/field-seed.php <group>
 *
 * Idempotent. The group id arrives in $args.
 *
 * @package CaluconEmbedGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cg_field_group = isset( $args[0] ) ? (string) $args[0] : '';

if ( function_exists( 'kses_remove_filters' ) ) {
	kses_remove_filters(); // Seed raw markup exactly as authored.
}

$cg_mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
if ( ! is_dir( $cg_mu_dir ) ) {
	wp_mkdir_p( $cg_mu_dir );
}

// ---------------------------------------------------------------------------
// The probe mu-plugin.
//
// /?cg_field=status answers a JSON document of the active plugins and their
// versions. Every spec's beforeAll reads it and FAILS unless the plugins its
// group names are there — a suite that ran against a site where the install
// step had quietly failed would be a test that cannot fail.
//
// The footer marker is for the cache groups: two anonymous requests that
// carry the SAME marker were served from a cache, whatever plugin wrote it.
// It survives HTML-comment stripping, which the plugins' own signatures do
// not.
// ---------------------------------------------------------------------------
$cg_probe = <<<'PHP'
<?php
/**
 * Plugin Name: CG field probe (test harness)
 * Description: Reports active plugins for tests/Field; prints a per-request marker. Never ships.
 */
add_action( 'init', static function () {
	if ( ! isset( $_GET['cg_field'] ) || 'status' !== $_GET['cg_field'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$active = array();
	foreach ( get_plugins() as $file => $data ) {
		if ( is_plugin_active( $file ) ) {
			$active[ dirname( $file ) === '.' ? basename( $file, '.php' ) : dirname( $file ) ] = (string) $data['Version'];
		}
	}
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Cache-Control: no-store' );
	echo wp_json_encode( array(
		'probe'  => 1,
		'wp'     => get_bloginfo( 'version' ),
		'php'    => PHP_VERSION,
		'active' => $active,
	) );
	exit;
}, 0 );
add_action( 'wp_footer', static function () {
	echo '<span hidden data-cg-field-req="' . esc_attr( uniqid( '', true ) ) . '"></span>' . "\n";
}, 1 );
PHP;
if ( false === file_put_contents( $cg_mu_dir . '/cg-field-probe.php', $cg_probe . "\n" ) ) {
	echo "field-seed: could not write the probe mu-plugin\n";
	exit( 1 );
}

// ---------------------------------------------------------------------------
// cmp-wp-consent-api: the WP Consent API plugin is only the API; a consent
// management plugin registers the consent TYPE (optin/optout) through it.
// Without one, wp_has_consent() is fail-open — the trap the bridge must not
// fall into. This stub plays the CMP, and stands down when the request
// carries ?cg_field_cmp=0 so the same site can show both states.
// ---------------------------------------------------------------------------
if ( 'cmp-wp-consent-api' === $cg_field_group ) {
	$cg_cmp_stub = <<<'PHP'
<?php
/**
 * Plugin Name: CG field consent-type stub (test harness)
 * Description: Plays the consent-management plugin that registers an opt-in consent type with WP Consent API. Never ships.
 */
add_action( 'plugins_loaded', static function () {
	if ( isset( $_GET['cg_field_cmp'] ) && '0' === $_GET['cg_field_cmp'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	add_filter( 'wp_get_consent_type', static function () {
		return 'optin';
	} );
	add_action( 'wp_footer', static function () {
		echo '<script>window.wp_consent_type = "optin"; document.dispatchEvent( new CustomEvent( "wp_consent_type_defined" ) );</script>' . "\n";
	}, 0 );
} );
PHP;
	if ( false === file_put_contents( $cg_mu_dir . '/cg-field-consent-cmp.php', $cg_cmp_stub . "\n" ) ) {
		echo "field-seed: could not write the consent-type stub\n";
		exit( 1 );
	}
}

// ---------------------------------------------------------------------------
// builder-elementor: two pages built the way Elementor stores them, so the
// builder renders them on the front end. One HTML widget carrying the same
// YouTube iframe as the fixture corpus; one native video widget, which is
// the case nothing server-side can see if Elementor fetches YouTube's
// player API itself.
// ---------------------------------------------------------------------------
if ( 'builder-elementor' === $cg_field_group ) {
	$cg_iframe = '<iframe title="Kolkja Cycling" width="500" height="281" src="https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed" frameborder="0" allow="accelerometer; autoplay; encrypted-media" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';
	$cg_pages  = array(
		'elementor-html'  => array(
			'title'  => 'Elementor HTML widget',
			'widget' => array(
				'id'         => 'cg000001',
				'elType'     => 'widget',
				'widgetType' => 'html',
				'settings'   => array( 'html' => $cg_iframe ),
				'elements'   => array(),
			),
		),
		'elementor-video' => array(
			'title'  => 'Elementor video widget',
			'widget' => array(
				'id'         => 'cg000002',
				'elType'     => 'widget',
				'widgetType' => 'video',
				'settings'   => array(
					'video_type'  => 'youtube',
					'youtube_url' => 'https://www.youtube.com/watch?v=y_pjE_p1HwE',
				),
				'elements'   => array(),
			),
		),
	);
	foreach ( $cg_pages as $cg_slug => $cg_page ) {
		$cg_data = array(
			array(
				'id'       => 'cg0000s1',
				'elType'   => 'section',
				'settings' => array(),
				'elements' => array(
					array(
						'id'       => 'cg0000c1',
						'elType'   => 'column',
						'settings' => array( '_column_size' => 100 ),
						'elements' => array( $cg_page['widget'] ),
					),
				),
			),
		);
		$cg_existing = get_page_by_path( $cg_slug, OBJECT, 'page' );
		$cg_post_id  = $cg_existing ? (int) $cg_existing->ID : (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $cg_slug,
				'post_title'   => $cg_page['title'],
				'post_content' => '<p>Elementor page: content lives in _elementor_data.</p>',
			),
			true
		);
		update_post_meta( $cg_post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $cg_post_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $cg_post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
		update_post_meta( $cg_post_id, '_elementor_data', wp_slash( wp_json_encode( $cg_data ) ) );
	}
}

flush_rewrite_rules();
echo "field-seed: done for group '{$cg_field_group}'\n";
