<?php
/**
 * Router for the E2E test server (php -S).
 *
 * Serves pages whose embed markup went through the real PHP pipeline
 * (HtmlScanner → HostMatcher → Registry → PlaceholderRenderer via
 * IframeRule) plus the plugin's actual front-end assets — no WordPress, but
 * nothing mocked on the path the product claim depends on.
 *
 * @package ConsentGate
 */

declare( strict_types=1 );

$root = dirname( __DIR__, 3 );

spl_autoload_register(
	static function ( $class ) use ( $root ) {
		$prefixes = array(
			'ConsentGate\\Tests\\' => $root . '/tests/',
			'ConsentGate\\'        => $root . '/src/',
		);
		foreach ( $prefixes as $prefix => $dir ) {
			if ( 0 === strpos( $class, $prefix ) ) {
				$path = $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( is_file( $path ) ) {
					require $path;
				}
				return;
			}
		}
	}
);

use ConsentGate\Tests\Support\PipelineFactory;

$uri = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

if ( '/healthz' === $uri ) {
	header( 'Content-Type: text/plain' );
	echo 'ok';
	return true;
}

if ( '/assets/gate.js' === $uri ) {
	header( 'Content-Type: application/javascript' );
	readfile( $root . '/assets/js/gate.js' );
	return true;
}

if ( '/assets/gate.css' === $uri ) {
	header( 'Content-Type: text/css' );
	readfile( $root . '/assets/css/gate.css' );
	return true;
}

if ( '/frame.html' === $uri ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><meta charset="utf-8"><title>Same-origin frame</title><p>local frame</p>';
	return true;
}

if ( '/page/gated' === $uri ) {
	// Raw content as WordPress would render it, before gating: one embed per
	// authoring style from the fixture corpus, plus a same-origin iframe that
	// must survive untouched.
	$content = implode(
		"\n",
		array(
			'<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">',
			'<iframe title="Kolkja Cycling" width="500" height="281" src="https://www.youtube.com/embed/y_pjE_p1HwE?feature=oembed" frameborder="0" allow="accelerometer; autoplay; encrypted-media" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
			'</div></figure>',
			"<div\nclass=wp-block-embed__wrapper> <iframe\nloading=lazy title=\"Minified\" width=422 height=750 src=\"https://www.youtube-nocookie.com/embed/y_pjE_p1HwE\" frameborder=0></iframe> </div>",
			'<iframe src="//player.vimeo.com/video/76979871" title="Vimeo" width="640" height="360"></iframe>',
			'<iframe src="https://widgets.example-partner.com/embed/9" title="Unknown widget" sandbox="allow-scripts" width="400" height="300"></iframe>',
			'<iframe src="/frame.html" title="Same origin" width="300" height="100"></iframe>',
		)
	);

	cg_e2e_page( $content );
	return true;
}

if ( '/page/scripts' === $uri ) {
	// Script-strategy providers: companion element + SDK script tag.
	$content = implode(
		"\n",
		array(
			'<blockquote class="twitter-tweet"><p lang="en" dir="ltr">Worth every kilometre.</p>&mdash; Calucon (@calucon) <a href="https://twitter.com/calucon/status/1234567890123456789">June 1, 2024</a></blockquote> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
			'<div class="strava-embed-placeholder" data-embed-type="activity" data-embed-id="1234567890"></div><script src="https://strava-embeds.com/embed.js"></script>',
		)
	);

	cg_e2e_page( $content );
	return true;
}

if ( '/page/aspect' === $uri ) {
	// The §5.3 layout-preservation cases: a core reserved aspect box
	// (wp-has-aspect-ratio + ::before spacer, iframe lifted out of flow),
	// and a bare iframe with only width/height attributes.
	$content = implode(
		"\n",
		array(
			'<figure class="wp-block-embed is-type-video wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">',
			'<iframe title="Reserved box" width="500" height="281" src="https://www.youtube.com/embed/y_pjE_p1HwE" frameborder="0" allowfullscreen></iframe>',
			'</div></figure>',
			'<div style="width: 640px;"><iframe src="https://widgets.example-partner.com/embed/9" title="Bare" width="640" height="360"></iframe></div>',
		)
	);

	// Equivalent of core's wp-embed-responsive rules, which real WordPress
	// themes ship; the harness must reproduce the trap to test the fix.
	$core_css = '.wp-block-embed{margin:0;max-width:600px;}'
		. '.wp-has-aspect-ratio .wp-block-embed__wrapper::before{content:"";display:block;padding-top:56.25%;}'
		. '.wp-has-aspect-ratio iframe{position:absolute;top:0;left:0;width:100%;height:100%;}';

	cg_e2e_page( $content, $core_css );
	return true;
}

/**
 * Gate raw content through the real pipeline and emit a full page.
 *
 * @param string $content   Pre-gating content HTML.
 * @param string $extra_css Page-specific CSS (theme/core simulation).
 * @return void
 */
function cg_e2e_page( string $content, string $extra_css = '' ) {
	$gated = PipelineFactory::gate(
		$content,
		array( '127.0.0.1', 'localhost' ),
		array( 'integration' => 'e2e' )
	);

	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width, initial-scale=1">'
		. '<title>Consent Gate E2E</title>'
		. '<link rel="stylesheet" href="/assets/gate.css">'
		. ( '' !== $extra_css ? '<style>' . $extra_css . '</style>' : '' )
		. '</head><body>'
		// A real theme provides the page scaffold (landmark + h1); the panel
		// itself deliberately adds neither (PLAN.md §5.1).
		. '<main><h1>Consent Gate E2E</h1>'
		. $gated
		. '</main>'
		. '<script src="/assets/gate.js"></script>'
		. '</body></html>';
}

http_response_code( 404 );
header( 'Content-Type: text/plain' );
echo 'not found';
return true;
