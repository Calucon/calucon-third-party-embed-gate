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
// builder-elementor: pages built the way Elementor stores them, so the
// builder renders them on the front end. One HTML widget carrying the same
// YouTube iframe as the fixture corpus; one native video widget, which is
// the case nothing server-side can see if Elementor fetches YouTube's
// player API itself; and, further down, the other players Elementor builds
// in the browser.
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

	// -----------------------------------------------------------------------
	// Players Elementor builds in the browser, which no server-side rule sees
	// unless it reads the same settings Elementor does (measured 2026-11-18,
	// Elementor 4.4.1 — every one of these contacted YouTube before a click):
	//
	// - the ATOMIC YouTube element (Editor V4, on by default for a site
	//   first installed at 4.0 or later): an empty <div data-e-type=
	//   "e-youtube"> and its own handler script;
	// - a section's and a container's BACKGROUND video: a
	//   background_video_link setting on the element and nothing else.
	//
	// The atomic Google Maps element renders a real iframe; it is here so a
	// change of that is noticed.
	// -----------------------------------------------------------------------
	$cg_yt     = 'https://www.youtube.com/watch?v=y_pjE_p1HwE';
	$cg_string = static function ( $value ) {
		return array(
			'$$type' => 'string',
			'value'  => $value,
		);
	};
	$cg_atomic = static function ( string $id, string $type, array $settings ) {
		return array(
			array(
				'id'       => $id . 'f',
				'elType'   => 'e-flexbox',
				'settings' => new stdClass(),
				'styles'   => new stdClass(),
				'elements' => array(
					array(
						'id'         => $id,
						'elType'     => 'widget',
						'widgetType' => $type,
						'settings'   => $settings,
						'elements'   => array(),
						'styles'     => new stdClass(),
					),
				),
			),
		);
	};
	$cg_heading = array(
		'id'         => 'cg0000h9',
		'elType'     => 'widget',
		'widgetType' => 'heading',
		'settings'   => array( 'title' => 'Over a background video' ),
		'elements'   => array(),
	);
	$cg_built   = array(
		'elementor-atomic-youtube'         => array(
			'Elementor atomic YouTube element',
			$cg_atomic( 'cgat001', 'e-youtube', array( 'source' => $cg_string( $cg_yt ) ) ),
		),
		'elementor-atomic-youtube-privacy' => array(
			'Elementor atomic YouTube element, privacy mode and lazy load',
			$cg_atomic(
				'cgat002',
				'e-youtube',
				array(
					'source'       => $cg_string( $cg_yt ),
					'privacy_mode' => array(
						'$$type' => 'boolean',
						'value'  => true,
					),
					'lazyload'     => array(
						'$$type' => 'boolean',
						'value'  => true,
					),
				)
			),
		),
		'elementor-atomic-maps'            => array(
			'Elementor atomic Google Maps element',
			$cg_atomic( 'cgat003', 'e-google-maps', array( 'location' => $cg_string( 'Tallinn' ) ) ),
		),
		'elementor-background-section'     => array(
			'Elementor section with a YouTube background',
			array(
				array(
					'id'       => 'cg0000s9',
					'elType'   => 'section',
					'settings' => array(
						'background_background'     => 'video',
						'background_video_link'     => $cg_yt,
						'background_play_on_mobile' => 'yes',
					),
					'elements' => array(
						array(
							'id'       => 'cg0000c9',
							'elType'   => 'column',
							'settings' => array( '_column_size' => 100 ),
							'elements' => array( $cg_heading ),
						),
					),
				),
			),
		),
		'elementor-background-container'   => array(
			'Elementor container with a Vimeo background',
			array(
				array(
					'id'       => 'cg0000k9',
					'elType'   => 'container',
					'settings' => array(
						'background_background'     => 'video',
						'background_video_link'     => 'https://vimeo.com/76979871',
						'background_play_on_mobile' => 'yes',
					),
					'elements' => array( $cg_heading ),
				),
			),
		),
	);
	foreach ( $cg_built as $cg_slug => $cg_page ) {
		$cg_existing = get_page_by_path( $cg_slug, OBJECT, 'page' );
		$cg_post_id  = $cg_existing ? (int) $cg_existing->ID : (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $cg_slug,
				'post_title'   => $cg_page[0],
				'post_content' => '<p>Elementor page: content lives in _elementor_data.</p>',
			),
			true
		);
		update_post_meta( $cg_post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $cg_post_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $cg_post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.0.0' );
		update_post_meta( $cg_post_id, '_elementor_data', wp_slash( wp_json_encode( $cg_page[1] ) ) );
	}
}

// ---------------------------------------------------------------------------
// The other free builders (measured 2026-11-18): players that are in the page
// only as an attribute or a <video> source, built in the browser by the
// builder's own script. Each group seeds through the builder's own storage,
// so what is rendered is what the builder renders.
//
// An attachment for the overlay images, and a server-side guard: none of
// these builders may make the WordPress server fetch a provider (Premium
// Addons looks Vimeo thumbnails up, SiteOrigin asks oEmbed) — the harness
// must not contact a provider on the plugin's behalf.
// ---------------------------------------------------------------------------
if ( in_array( $cg_field_group, array( 'builder-elementor-addons', 'builder-beaver', 'builder-siteorigin' ), true ) ) {
	$cg_guard = <<<'PHP'
<?php
/**
 * Plugin Name: CG field HTTP guard (test harness)
 * Description: Refuses server-side requests to anything but wordpress.org and this site. Never ships.
 */
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( preg_match( '/(^|\.)(wordpress\.org|w\.org)$/', $host ) || in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) {
		return $pre;
	}
	return new WP_Error( 'cg_field_blocked', 'blocked by the field harness' );
}, 10, 3 );
PHP;
	if ( false === file_put_contents( $cg_mu_dir . '/cg-field-http-guard.php', $cg_guard . "\n" ) ) {
		echo "field-seed: could not write the HTTP guard\n";
		exit( 1 );
	}
	wp_set_current_user( 1 );

	$cg_overlay = get_page_by_path( 'cg-overlay', OBJECT, 'attachment' );
	if ( $cg_overlay ) {
		$cg_overlay_id = (int) $cg_overlay->ID;
	} else {
		$cg_upload     = wp_upload_dir();
		$cg_overlay_id = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'cg-overlay',
				'post_name'      => 'cg-overlay',
				'post_status'    => 'inherit',
			),
			$cg_upload['path'] . '/cg-overlay.png'
		);
		// Metadata only, as in tests/wp/seed.php: the tests assert markup.
		update_post_meta( $cg_overlay_id, '_wp_attached_file', ltrim( $cg_upload['subdir'], '/' ) . '/cg-overlay.png' );
	}
	$cg_overlay_image = array(
		'id'  => $cg_overlay_id,
		'url' => (string) wp_get_attachment_url( $cg_overlay_id ),
	);
	$cg_youtube       = 'https://www.youtube.com/watch?v=y_pjE_p1HwE';
	$cg_vimeo         = 'https://vimeo.com/76979871';
	$cg_youtube_frame = '<iframe title="Kolkja Cycling" width="560" height="315" src="https://www.youtube.com/embed/y_pjE_p1HwE" frameborder="0" allow="accelerometer; autoplay; encrypted-media" allowfullscreen></iframe>';
}

// builder-elementor-addons: Essential Addons' Sticky Video (Plyr builds the
// player from two data attributes) and Premium Addons' Video Box (its script
// builds an iframe from data-src on page load, or shows the provider's own
// thumbnail). Stored as _elementor_data, like the builder-elementor pages.
if ( 'builder-elementor-addons' === $cg_field_group ) {
	$cg_widget = static function ( string $id, string $type, array $settings ): array {
		return array(
			array(
				'id'       => $id . 's',
				'elType'   => 'section',
				'settings' => array(),
				'elements' => array(
					array(
						'id'       => $id . 'c',
						'elType'   => 'column',
						'settings' => array( '_column_size' => 100 ),
						'elements' => array(
							array(
								'id'         => $id,
								'elType'     => 'widget',
								'widgetType' => $type,
								'settings'   => $settings,
								'elements'   => array(),
							),
						),
					),
				),
			),
		);
	};
	$cg_box    = array(
		'premium_video_box_video_type' => 'youtube',
		'youtube_list'                 => '',
		'premium_video_box_link'       => $cg_youtube,
	);
	$cg_addons = array(
		'addons-sticky-youtube'         => $cg_widget(
			'cgc0002',
			'eael-sticky-video',
			array(
				'eael_video_source'   => 'youtube',
				'eaelsv_link_youtube' => $cg_youtube,
			)
		),
		'addons-sticky-vimeo-overlay'   => $cg_widget(
			'cgc0003',
			'eael-sticky-video',
			array(
				'eael_video_source'      => 'vimeo',
				'eaelsv_link_vimeo'      => $cg_vimeo,
				'eaelsv_overlay_options' => 'yes',
				'eaelsv_overlay_image'   => $cg_overlay_image,
			)
		),
		'addons-videobox-youtube'       => $cg_widget( 'cgc0010', 'premium-addon-video-box', $cg_box ),
		'addons-videobox-own-image'     => $cg_widget(
			'cgc0011',
			'premium-addon-video-box',
			$cg_box + array(
				'premium_video_box_image_switcher' => 'yes',
				'premium_video_box_image'          => $cg_overlay_image,
			)
		),
		'addons-videobox-autoplay'      => $cg_widget(
			'cgc0016',
			'premium-addon-video-box',
			$cg_box + array(
				'premium_video_box_self_autoplay' => 'yes',
				'premium_video_box_mute'          => 'yes',
			)
		),
		// Overlay off: Premium Addons shows YouTube's own thumbnail.
		'addons-videobox-their-image'   => $cg_widget( 'cgc0017', 'premium-addon-video-box', $cg_box + array( 'premium_video_box_image_switcher' => '' ) ),
		'addons-videobox-vimeo'         => $cg_widget(
			'cgc0012',
			'premium-addon-video-box',
			array(
				'premium_video_box_video_type' => 'vimeo',
				'premium_video_box_link'       => $cg_vimeo,
			)
		),
		// Elementor's own widget with an image overlay over a real iframe.
		'addons-elementor-vimeo-overlay' => $cg_widget(
			'cgb0007',
			'video',
			array(
				'video_type'         => 'vimeo',
				'vimeo_url'          => $cg_vimeo,
				'show_image_overlay' => 'yes',
				'image_overlay'      => $cg_overlay_image,
			)
		),
	);
	foreach ( $cg_addons as $cg_slug => $cg_data ) {
		$cg_existing = get_page_by_path( $cg_slug, OBJECT, 'page' );
		$cg_post_id  = $cg_existing ? (int) $cg_existing->ID : (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $cg_slug,
				'post_title'   => $cg_slug,
				'post_content' => '<p>Elementor page: content lives in _elementor_data.</p>',
			),
			true
		);
		update_post_meta( $cg_post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $cg_post_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $cg_post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '4.0.0' );
		update_post_meta( $cg_post_id, '_elementor_data', wp_slash( wp_json_encode( $cg_data ) ) );
	}
}

// builder-beaver: rows with a YouTube, a Vimeo and an "embedded code"
// background, and the video module as the control. Stored through
// FLBuilderModel's own API, the way the editor does.
if ( 'builder-beaver' === $cg_field_group ) {
	if ( ! class_exists( 'FLBuilderModel' ) ) {
		echo "field-seed: Beaver Builder is not loaded\n";
		exit( 1 );
	}
	$cg_text  = array( 'html', array( 'html' => '<h2>Over a background video</h2>' ) );
	$cg_rows  = array(
		'beaver-background-youtube' => array(
			array(
				'bg_type'              => 'video',
				'bg_video_source'      => 'video_service',
				'bg_video_service_url' => $cg_youtube,
			),
			$cg_text,
		),
		'beaver-background-vimeo'   => array(
			array(
				'bg_type'              => 'video',
				'bg_video_source'      => 'video_service',
				'bg_video_service_url' => $cg_vimeo,
			),
			$cg_text,
		),
		'beaver-background-embed'   => array(
			array(
				'bg_type'       => 'embed',
				'bg_embed_code' => $cg_youtube_frame,
			),
			$cg_text,
		),
		'beaver-video-module'       => array(
			array(),
			array(
				'video',
				array(
					'video_type' => 'embed',
					'embed_code' => $cg_youtube_frame,
				),
			),
		),
	);
	foreach ( $cg_rows as $cg_slug => $cg_def ) {
		$cg_old = get_page_by_path( $cg_slug, OBJECT, 'page' );
		if ( $cg_old ) {
			wp_delete_post( $cg_old->ID, true );
		}
		$cg_post_id = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $cg_slug,
				'post_title'   => $cg_slug,
				'post_content' => '',
			)
		);
		FLBuilderModel::set_post_id( $cg_post_id );
		$cg_row = FLBuilderModel::add_row( '1-col' );
		if ( array() !== $cg_def[0] ) {
			FLBuilderModel::save_settings( $cg_row->node, $cg_def[0] );
		}
		$cg_column = null;
		foreach ( FLBuilderModel::get_layout_data() as $cg_node ) {
			if ( 'column' === $cg_node->type ) {
				$cg_column = $cg_node;
			}
		}
		$cg_settings = (object) array_merge( (array) FLBuilderModel::get_module_defaults( $cg_def[1][0] ), $cg_def[1][1] );
		if ( null === $cg_column || ! FLBuilderModel::add_module( $cg_def[1][0], $cg_settings, $cg_column->node ) ) {
			echo "field-seed: could not add the {$cg_def[1][0]} module to {$cg_slug}\n";
			exit( 1 );
		}
		// The editor works on the draft and publishes it; do the same so
		// save_layout() runs its real path.
		FLBuilderModel::update_layout_data( FLBuilderModel::get_layout_data( 'published', $cg_post_id ), 'draft', $cg_post_id );
		FLBuilderModel::update_layout_settings( FLBuilderModel::get_layout_settings( 'published', $cg_post_id ), 'draft', $cg_post_id );
		FLBuilderModel::save_layout();
		FLBuilderModel::reset_post_id();
	}
}

// builder-siteorigin: the video widget with "oEmbed" switched off, where
// MediaElement gets a provider's PAGE as a <video> source and builds the
// provider's player — and WordPress's own [video] shortcode, which does the
// same without any plugin. Stored through SiteOrigin's own save path.
if ( 'builder-siteorigin' === $cg_field_group ) {
	if ( ! class_exists( 'SiteOrigin_Widgets_Bundle' ) || ! class_exists( 'SiteOrigin_Panels_Admin' ) ) {
		echo "field-seed: SiteOrigin Page Builder and the Widgets Bundle are not both loaded\n";
		exit( 1 );
	}
	SiteOrigin_Widgets_Bundle::single()->activate_widget( 'video' );
	// Activating a widget registers its class on the next load; this one is
	// needed in this request.
	SiteOrigin_Widgets_Bundle::single()->load_widget_plugins();
	if ( function_exists( 'siteorigin_widget_register' ) && ! class_exists( 'SiteOrigin_Widget_Video_Widget' ) ) {
		echo "field-seed: SiteOrigin's video widget class did not load\n";
		exit( 1 );
	}
	$cg_video = static function ( string $url ): array {
		return array(
			'title'     => '',
			'host_type' => 'external',
			'video'     => array( 'external_video' => $url ),
			'playback'  => array(
				'autoplay' => false,
				'loop'     => false,
				'fitvids'  => true,
				'oembed'   => false,
			),
		);
	};
	foreach ( array(
		'siteorigin-video-youtube' => $cg_video( $cg_youtube ),
		'siteorigin-video-vimeo'   => $cg_video( $cg_vimeo ),
	) as $cg_slug => $cg_instance ) {
		$cg_old = get_page_by_path( $cg_slug, OBJECT, 'page' );
		if ( $cg_old ) {
			wp_delete_post( $cg_old->ID, true );
		}
		$cg_post_id                 = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $cg_slug,
				'post_title'   => $cg_slug,
				'post_content' => '',
			)
		);
		$cg_instance['panels_info'] = array(
			'class'     => 'SiteOrigin_Widget_Video_Widget',
			'raw'       => false,
			'grid'      => 0,
			'cell'      => 0,
			'id'        => 0,
			'widget_id' => wp_generate_uuid4(),
			'style'     => array(
				'background_image_attachment' => false,
				'background_display'          => 'tile',
			),
		);
		$_POST['_sopanels_nonce']   = wp_create_nonce( 'save' );
		$_POST['panels_data']       = wp_slash(
			wp_json_encode(
				array(
					'widgets'    => array( $cg_instance ),
					'grids'      => array(
						array(
							'cells'           => 1,
							'style'           => array(),
							'ratio'           => 1,
							'ratio_direction' => 'right',
						),
					),
					'grid_cells' => array(
						array(
							'grid'   => 0,
							'index'  => 0,
							'weight' => 1,
							'style'  => array(),
						),
					),
				)
			)
		);
		SiteOrigin_Panels_Admin::single()->save_post( $cg_post_id );
		$cg_saved = get_post_meta( $cg_post_id, 'panels_data', true );
		if ( ! is_array( $cg_saved ) || empty( $cg_saved['widgets'] ) ) {
			echo "field-seed: SiteOrigin did not store the widget for {$cg_slug}\n";
			exit( 1 );
		}
	}
	// SiteOrigin saves whatever panels_data is in the request on every
	// save_post — the next page would otherwise get the last widget.
	unset( $_POST['panels_data'], $_POST['_sopanels_nonce'] );
	if ( null === get_page_by_path( 'core-video-shortcode', OBJECT, 'page' ) ) {
		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => 'core-video-shortcode',
				'post_title'   => 'core-video-shortcode',
				'post_content' => '[video src="' . $cg_youtube . '" width="640" height="360"]',
			)
		);
	}
}

flush_rewrite_rules();
echo "field-seed: done for group '{$cg_field_group}'\n";
