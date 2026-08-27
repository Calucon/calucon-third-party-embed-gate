<?php
/**
 * Seeds the WordPress integration-test content. Idempotent — safe to run on
 * every stack start. Executed inside WordPress:
 *   Docker:      wp eval-file …/tests/wp/seed.php   (via tests/wp/setup.sh)
 *   Playground:  the runPHP step in tests/wp/blueprint.json
 *
 * The posts mirror the fixture corpus (PLAN.md §10.1) so the integration
 * layer is tested with the same shapes the unit fixtures pin down —
 * including the minified variant. No emoji anywhere: WordPress would fetch
 * twemoji images from s.w.org and taint the zero-third-party-request test.
 *
 * @package CaluconEmbedGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'kses_remove_filters' ) ) {
	kses_remove_filters(); // Seed raw markup exactly as authored.
}

// Poster attachment (§5.4): metadata is enough — wp_get_attachment_image_url()
// resolves a site-origin URL from _wp_attached_file without touching disk, and
// the poster test asserts markup, not pixels.
$cg_poster = get_page_by_path( 'cg-poster-image', OBJECT, 'attachment' );
if ( $cg_poster ) {
	$cg_poster_id = (int) $cg_poster->ID;
} else {
	$cg_poster_id = (int) wp_insert_post(
		array(
			'post_name'      => 'cg-poster-image',
			'post_title'     => 'Poster image',
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/jpeg',
		)
	);
	update_post_meta( $cg_poster_id, '_wp_attached_file', '2026/08/cg-poster.jpg' );
}

$cg_seed_posts = array(
	'gated-classic'  => array(
		'title'   => 'Gated classic content',
		'content' => '<p>Intro paragraph.</p>' . "\n\n"
			. '<iframe title="Kolkja Cycling" width="500" height="281" src="https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed" frameborder="0" allow="accelerometer; autoplay; encrypted-media" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>' . "\n\n"
			. "<div\nclass=wp-block-embed__wrapper> <iframe\nloading=lazy title=\"Minified\" width=422 height=750 src=\"https://www.youtube-nocookie.com/embed/y_pjE_p1HwE\" frameborder=0></iframe> </div>" . "\n\n"
			. '<iframe src="https://widgets.example-partner.com/embed/9" title="Unknown widget" sandbox="allow-scripts" width="400" height="300"></iframe>' . "\n\n"
			. '<iframe src="/wp-json/" title="Same origin" width="300" height="100"></iframe>' . "\n\n"
			. '<p>Outro paragraph.</p>',
	),
	'gated-blocks'   => array(
		'title'   => 'Gated block content',
		'content' => "<!-- wp:paragraph -->\n<p>Block intro.</p>\n<!-- /wp:paragraph -->\n\n"
			. "<!-- wp:html -->\n<figure class=\"wp-block-embed is-type-video wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\">\n<iframe title=\"Kolkja Cycling\" width=\"500\" height=\"281\" src=\"https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed\" frameborder=\"0\" allowfullscreen></iframe>\n</div></figure>\n<!-- /wp:html -->\n\n"
			. "<!-- wp:html -->\n<iframe src=\"https://player.vimeo.com/video/76979871?h=8272103f6e\" title=\"Vimeo\" width=\"640\" height=\"360\"></iframe>\n<!-- /wp:html -->",
	),
	'script-embeds'  => array(
		'title'   => 'Script embeds',
		'content' => '<blockquote class="twitter-tweet"><p lang="en" dir="ltr">Worth every kilometre.</p>&mdash; Calucon (@calucon) <a href="https://twitter.com/calucon/status/1234567890123456789">June 1, 2024</a></blockquote> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>' . "\n\n"
			. '<div class="strava-embed-placeholder" data-embed-type="activity" data-embed-id="1234567890"></div><script src="https://strava-embeds.com/embed.js"></script>',
	),
	'poster-embed'   => array(
		'title'   => 'Poster embed',
		'content' => "<!-- wp:html {\"caluconEmbedGatePoster\":" . $cg_poster_id . "} -->\n<figure class=\"wp-block-embed is-type-video wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\">\n<iframe title=\"Kolkja Cycling\" width=\"500\" height=\"281\" src=\"https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed\" frameborder=\"0\" allowfullscreen></iframe>\n</div></figure>\n<!-- /wp:html -->",
	),
	'no-embeds'      => array(
		'title'   => 'No embeds here',
		'content' => '<p>Plain content without any third-party embed. The plugin must ship no assets on this page.</p>',
	),
	'escaped-markup' => array(
		'title'   => 'How to embed a video',
		'content' => '<p>To embed a video, paste this into your post:</p>' . "\n"
			. '<p><code>&lt;iframe src="https://www.youtube.com/embed/y_pjE_p1HwE" width="560" height="315"&gt;&lt;/iframe&gt;</code></p>',
	),
	'withdraw-page'  => array(
		'title'   => 'Privacy tools',
		'content' => '<p>Manage stored embed consents:</p>' . "\n" . '[calucon_embed_gate_withdraw]',
	),
	// Per-embed texts set in the block editor (RenderBlock): markup is
	// stripped and the texts are capped (button 120, notice 400).
	'per-embed-text' => array(
		'title'   => 'Per-embed text',
		'content' => "<!-- wp:html {\"caluconEmbedGateAction\":\"Load <b>the trailer</b>\",\"caluconEmbedGateNote\":\"<em>Own notice.</em> " . str_repeat( 'x', 500 ) . "\"} -->\n"
			. '<iframe src="https://player.vimeo.com/video/76979871" title="Trailer" width="640" height="360"></iframe>' . "\n<!-- /wp:html -->",
	),
);

foreach ( $cg_seed_posts as $cg_slug => $cg_post ) {
	if ( null !== get_page_by_path( $cg_slug, OBJECT, 'post' ) ) {
		continue;
	}
	wp_insert_post(
		array(
			'post_name'    => $cg_slug,
			'post_title'   => $cg_post['title'],
			'post_content' => $cg_post['content'],
			'post_status'  => 'publish',
			'post_type'    => 'post',
		)
	);
}

// A performance-plugin emulator: adds a preconnect for a gated provider and
// a safe CDN through the wp_resource_hints filter (priority 5, before the
// plugin's own filter at 10), and prints literal <link> hint tags into
// wp_head the way Perfmatters-class plugins do — bypassing every filter
// (§9.14). The integration tests assert the filter path is always cleaned
// and the literal path is cleaned exactly when output buffering is on.
$cg_mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
if ( ! is_dir( $cg_mu_dir ) && ! mkdir( $cg_mu_dir, 0755, true ) && ! is_dir( $cg_mu_dir ) ) {
	// Fail loudly. A silent mkdir failure (wrong container user, read-only
	// volume) leaves the hint emulator and the code-registered provider
	// missing, and three integration tests then fail as if the PLUGIN were
	// broken. Seeding must not half-succeed.
	fwrite( STDERR, "seed: cannot create $cg_mu_dir — check the container user and volume permissions\n" );
	exit( 1 );
}
$cg_mu_source = <<<'MUPLUGIN'
<?php
add_filter( 'wp_resource_hints', function ( $urls, $rel ) {
	if ( 'preconnect' === $rel ) {
		$urls[] = 'https://platform.twitter.com';
		$urls[] = 'https://cdn.filter-safe.example';
	}
	return $urls;
}, 5, 2 );
// A code-registered provider (docs/customizing.md "Adding a provider"): the
// integration tests assert an owner-defined row cannot take its host.
add_filter( 'calucon_embed_gate_providers', function ( array $providers ): array {
	$providers[] = array(
		'id'          => 'partner-code',
		'label'       => 'Partner (code)',
		'match'       => array( 'iframe_host' => array( 'code.example-partner.com' ) ),
		'load_host'   => 'code-nocookie.example-partner.com',
		'privacy_url' => 'https://code.example-partner.com/privacy',
		'kind'        => 'document',
	);
	return $providers;
} );
add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://www.youtube.com">' . "\n";
	echo '<link rel="preconnect" href="https://cdn.literal-safe.example">' . "\n";
}, 99 );
// Locale switch for the translation tests: ?cg_locale=de_DE renders that one
// request in German. Switching the site language would need core's German
// language pack, which the offline Playground image does not have — this
// needs only the plugin's own bundled .mo and .json files, which is exactly
// what the tests are there to prove.
add_filter( 'locale', function ( $locale ) {
	$requested = isset( $_GET['cg_locale'] ) ? (string) $_GET['cg_locale'] : '';
	// de_DE, de_DE_formal, de_AT, de_CH, de_CH_informal.
	return preg_match( '/^[a-z]{2}_[A-Z]{2}(?:_[a-z]+)?$/', $requested ) ? $requested : $locale;
} );
// WPML's presence is detected by the constant it defines. ?cg_wpml=1 makes
// the Compatibility screen see one, without installing WPML.
if ( isset( $_GET['cg_wpml'] ) && ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
	define( 'ICL_SITEPRESS_VERSION', '4.6.13' );
}
// Cache-flush recorder + readback, for the CacheFlush investigation. Records
// on calucon_embed_gate_flush_caches, which flush_all() fires unconditionally,
// so it does not depend on which purge function happens to exist here.
add_action(
	'calucon_embed_gate_flush_caches',
	static function () {
		update_option( 'cg_flushes', (int) get_option( 'cg_flushes', 0 ) + 1 );
	}
);
if ( isset( $_GET['cg_flushes'] ) ) {
	add_action(
		'init',
		static function () {
			// ?cg_flushes=reset puts the site back to "settings never saved",
			// which is the state the first-save flush is about and which any
			// earlier test in the run has already destroyed by saving.
			if ( 'reset' === (string) $_GET['cg_flushes'] ) {
				delete_option( 'calucon_embed_gate_options' );
				update_option( 'cg_flushes', 0 );
			}
			header( 'Content-Type: text/plain' );
			printf(
				'flushes=%d row_exists=%d',
				(int) get_option( 'cg_flushes', 0 ),
				null === get_option( 'calucon_embed_gate_options', null ) ? 0 : 1
			);
			exit;
		},
		1
	);
}

// Consent-platform and page-builder emulators. Neither branch of the
// Compatibility screen had ever rendered in a test: the CMP rows say whether
// the bridge is active, merely available, or absent because the platform is
// untested, and the builder row changes with whole-page gating. All of it is
// copy an owner acts on.
//
//   ?cg_cmp=tested    — a bridgeable platform (Complianz)
//   ?cg_cmp=untested  — one with no tested bridge (Usercentrics)
//   ?cg_builder=1     — a page builder (Elementor)
if ( isset( $_GET['cg_cmp'] ) ) {
	if ( 'tested' === $_GET['cg_cmp'] && ! defined( 'cmplz_version' ) ) {
		define( 'cmplz_version', '7.1.0' );
	}
	if ( 'untested' === $_GET['cg_cmp'] && ! defined( 'UC_PLUGIN_FILE' ) ) {
		define( 'UC_PLUGIN_FILE', 'usercentrics/usercentrics.php' );
	}
}
if ( isset( $_GET['cg_builder'] ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
	define( 'ELEMENTOR_VERSION', '3.21.0' );
}

// Asset-CDN emulator. The 0.13.0 fix has two halves and only one of them was
// reachable by a fixture: the path heuristic (/wp-content/ on any host) is
// covered by tests/Fixtures/cdn-offloaded-assets-*, but Plugin::own_hosts() —
// which reads content_url(), plugins_url(), includes_url(), the uploads base
// and both theme URIs so that a CDN plugin filtering them is trusted
// automatically — was reached by NO test at all. tests/Support/PipelineFactory
// builds its HostMatcher from a literal array, so every unit and fixture test
// goes around the wiring in Plugin::pipeline().
//
// The emitted script deliberately sits at a path with no /wp-content/ in it,
// so the path heuristic cannot rescue it: if it survives ungated, that is
// own_hosts() doing the work and nothing else.
//
//   ?cg_cdn=1  — content_url()/plugins_url() moved to the CDN host, so the
//                CDN host is one of the site's own
//   ?cg_cdn=0  — the same script, without the filters: the control, which
//                must be gated
if ( isset( $_GET['cg_cdn'] ) ) {
	if ( '1' === (string) $_GET['cg_cdn'] ) {
		$cg_cdn_host = static function ( $url ) {
			return preg_replace( '#^https?://[^/]+#', 'https://cdn.cg-offload.example', (string) $url );
		};
		add_filter( 'content_url', $cg_cdn_host );
		add_filter( 'plugins_url', $cg_cdn_host );
	}
	// wp_footer, not wp_head: OutputBuffer gates only inside <body>, on
	// purpose — a panel in the head is invalid markup, and head findings are
	// the Status screen's job rather than a silent rewrite.
	add_action(
		'wp_footer',
		static function () {
			echo '<script src="https://cdn.cg-offload.example/bundle.js" id="cg-cdn-probe"></script>' . "\n";
		},
		1
	);
}

// Cache-plugin emulator, the same trick. The Compatibility screen tells the
// owner which of their optimiser's settings breaks embeds and WHERE that
// plugin keeps its exclusion list — advice that renders only when a cache
// plugin is detected, so on a clean Playground it never renders at all and
// was untested end to end.
//
//   ?cg_cache=w3tc   — detected, no settings reader exists for it, so the
//                      screen must say so rather than imply an all-clear
//   ?cg_cache=rocket — detected AND its delay_js setting readable, which is
//                      the one that costs the visitor a click
if ( isset( $_GET['cg_cache'] ) ) {
	if ( 'w3tc' === $_GET['cg_cache'] && ! defined( 'W3TC' ) ) {
		define( 'W3TC', true );
	}
	if ( 'autoptimize' === $_GET['cg_cache'] ) {
		// The 'combine' state, which had never rendered. Autoptimize's reader
		// needs autoptimize_js set AND autoptimize_js_aggregate truthy — the
		// latter defaults to true, so its default_option_ filter matters as
		// much as the option_ one.
		if ( ! defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) ) {
			define( 'AUTOPTIMIZE_PLUGIN_VERSION', '3.1' );
		}
		$cg_ao = static function () {
			return '1';
		};
		add_filter( 'option_autoptimize_js', $cg_ao );
		add_filter( 'default_option_autoptimize_js', $cg_ao );
		add_filter( 'option_autoptimize_js_aggregate', $cg_ao );
		add_filter( 'default_option_autoptimize_js_aggregate', $cg_ao );
	}
	if ( 'litespeed' === $_GET['cg_cache'] ) {
		// The 'off' state: settings READ, and none of the risky ones on. It
		// must never collapse into 'unknown' — "we looked and it is fine" and
		// "we could not look" are different things to tell an owner.
		if ( ! defined( 'LSCWP_V' ) ) {
			define( 'LSCWP_V', '6.5' );
		}
		$cg_ls_off = static function () {
			return '0';
		};
		add_filter( 'option_litespeed.optm.js_defer', $cg_ls_off );
		add_filter( 'default_option_litespeed.optm.js_defer', $cg_ls_off );
		add_filter( 'option_litespeed.optm.js_comb', $cg_ls_off );
		add_filter( 'default_option_litespeed.optm.js_comb', $cg_ls_off );
	}
	if ( 'rocket' === $_GET['cg_cache'] ) {
		if ( ! defined( 'WP_ROCKET_VERSION' ) ) {
			define( 'WP_ROCKET_VERSION', '3.15' );
		}
		// Both filters, deliberately: option_{$name} fires only when the
		// option row EXISTS, and this emulator never writes one. A site that
		// never saved WP Rocket's settings hits default_option_{$name}
		// instead — the same trap the multilingual emulator documents, and
		// hooking only the first would silently emulate nothing.
		$cg_rocket = static function () {
			return array( 'delay_js' => 1, 'minify_concatenate_js' => 0 );
		};
		add_filter( 'option_wp_rocket_settings', $cg_rocket );
		add_filter( 'default_option_wp_rocket_settings', $cg_rocket );
	}
}
// Multilingual emulator: WPML and Polylang translate the strings named in
// wpml-config.xml by filtering the option as the page is built, in that
// page's language — long after plugins_loaded. ?cg_translate=1 does the same
// thing, and also tries to switch OFF a provider, which a translation layer
// must never be able to do from here.
add_action( 'init', function () {
	if ( ! isset( $_GET['cg_translate'] ) ) {
		return;
	}
	$translate = function ( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$value['providers']['youtube']['action']      = 'Video abspielen (übersetzt)';
		$value['providers']['youtube']['note']        = 'Übersetzter Hinweistext für dieses Video.';
		$value['providers']['youtube']['privacy_url'] = 'https://policies.google.com/privacy?hl=de';
		$value['providers']['youtube']['enabled']     = false;
		$value['detection']['never_gate']             = array( 'www.youtube.com' );
		return $value;
	};
	// A site that never saved the settings has no option row, and WordPress
	// then applies default_option_ instead of option_ — cover both, the way
	// a translation layer has to.
	add_filter( 'option_calucon_embed_gate_options', $translate );
	add_filter( 'default_option_calucon_embed_gate_options', $translate );
} );
MUPLUGIN;
if ( false === file_put_contents( $cg_mu_dir . '/cg-test-hints.php', $cg_mu_source . "\n" ) ) {
	fwrite( STDERR, "seed: cannot write the hint emulator into $cg_mu_dir\n" );
	exit( 1 );
}

// Pretty permalinks so the tests can address posts by slug. Flush
// unconditionally: newer Playground images pre-set the structure WITHOUT
// building the rules, so a structure-changed guard skips the flush and the
// first request after boot 404s (the old cause of a flaky first test).
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
flush_rewrite_rules();

echo "calucon-third-party-embed-gate: seed complete\n";
