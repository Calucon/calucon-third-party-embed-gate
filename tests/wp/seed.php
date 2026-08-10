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
 * @package ConsentGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'kses_remove_filters' ) ) {
	kses_remove_filters(); // Seed raw markup exactly as authored.
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
		'content' => '<p>Manage stored embed consents:</p>' . "\n" . '[consent_gate_withdraw]',
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

// Pretty permalinks so the tests can address posts by slug.
if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
	$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
	flush_rewrite_rules();
}

echo "consent-gate: seed complete\n";
