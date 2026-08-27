<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Detection\HostMatcher;
use PHPUnit\Framework\TestCase;

final class HostMatcherTest extends TestCase {

	public function test_foreign_host_is_foreign(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://www.youtube.com/embed/x' ) );
	}

	public function test_own_host_and_www_equivalence(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://example.test/player' ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://www.example.test/player' ) );
	}

	public function test_www_equivalence_can_be_disabled(): void {
		$matcher = new HostMatcher( array( 'example.test' ), false );

		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://www.example.test/player' ) );
	}

	public function test_relative_urls_are_own(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( '/frame.html' ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( 'frame.html' ) );
	}

	public function test_non_loading_schemes_are_skipped(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::SKIP, $matcher->classify( '' ) );
		self::assertSame( HostMatcher::SKIP, $matcher->classify( 'about:blank' ) );
		self::assertSame( HostMatcher::SKIP, $matcher->classify( 'data:text/html,hi' ) );
		self::assertSame( HostMatcher::SKIP, $matcher->classify( 'blob:https://a.example/uuid' ) );
		self::assertSame( HostMatcher::SKIP, $matcher->classify( 'javascript:void(0)' ) );
	}

	public function test_protocol_relative_urls_resolve_by_host(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( '//example.test/frame' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( '//player.vimeo.com/video/1' ) );
	}

	public function test_wildcard_own_hosts(): void {
		$matcher = new HostMatcher( array( 'example.test', '*.cdn.example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://eu1.cdn.example.test/frame' ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://cdn.example.test/frame' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://cdn.example.evil/frame' ) );
	}

	public function test_idn_and_punycode_compare_equal(): void {
		if ( ! function_exists( 'idn_to_ascii' ) ) {
			self::markTestSkipped( 'ext-intl is not available; IDN equivalence needs idn_to_ascii()' );
		}
		$matcher = new HostMatcher( array( 'münchen.example' ) );

		self::assertTrue( $matcher->is_own_host( 'xn--mnchen-3ya.example' ) );
		self::assertTrue( $matcher->is_own_host( 'MÜNCHEN.example.' ) );
	}

	/**
	 * parse_url() and the browser must not disagree on the authority: a URL
	 * whose real (browser) host is a third party must never be classified OWN
	 * (invariant 6). Browsers treat a backslash as a slash for special schemes
	 * and ignore extra/missing authority slashes, so these all connect to
	 * evil.example even though naive parse_url() reads the own host after '@'.
	 */
	public function test_authority_confusion_is_gated_not_own(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://evil.example\\@example.test/track' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( '//evil.example\\@example.test/track' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https:/\\/evil.example/track' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https:\\\\evil.example/track' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https:evil.example/track' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( '/\\evil.example/track' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( '///evil.example/track' ) );
	}

	/**
	 * The mirror of the above: a backslash/irregular-slash URL whose real host
	 * IS the own host must stay OWN, and a genuine same-origin absolute path
	 * (single leading slash) must never be mistaken for protocol-relative.
	 */
	public function test_authority_normalisation_keeps_own_and_paths_own(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https:\\\\example.test/frame' ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://evil.example%5C@example.test/frame' ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( "https://example.test\t/frame" ) );
		self::assertSame( HostMatcher::OWN, $matcher->classify( '/frame.html' ) );
	}

	public function test_host_of_matches_classify_normalisation(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		self::assertSame( 'evil.example', $matcher->host_of( 'https://evil.example\\@example.test/track' ) );
		self::assertSame( 'evil.example', $matcher->host_of( 'https:/\\/evil.example/track' ) );
	}

	/**
	 * The escape hatch for a CDN that rewrites the finished HTML: the site's
	 * own asset tree on a foreign host is recognised by its path, so
	 * ScriptRule and StylesheetRule can let it through instead of gating the
	 * site's own JavaScript into a placeholder.
	 */
	public function test_own_asset_paths_are_recognised_on_any_host(): void {
		self::assertTrue( HostMatcher::looks_like_own_asset_path( 'https://cdn.example.net/wp-includes/js/dist/i18n.min.js' ) );
		self::assertTrue( HostMatcher::looks_like_own_asset_path( 'https://cdn.example.net/wp-content/themes/x/app.js' ) );
		// WordPress in a subdirectory, and CDN pull-zone prefixes: substring,
		// never prefix.
		self::assertTrue( HostMatcher::looks_like_own_asset_path( 'https://cdn.example.net/zone7/blog/wp-content/plugins/x/a.js' ) );
		// Protocol-relative and uppercase paths are the same URL to a browser.
		self::assertTrue( HostMatcher::looks_like_own_asset_path( '//cdn.example.net/WP-INCLUDES/js/dist/i18n.min.js' ) );
	}

	/**
	 * The exemption must stay narrow: anything that is not the WordPress asset
	 * tree is still a third-party URL, including a query string that merely
	 * mentions one.
	 */
	public function test_other_paths_are_not_own_assets(): void {
		self::assertFalse( HostMatcher::looks_like_own_asset_path( 'https://cdn.example.net/sdk/embed.js' ) );
		self::assertFalse( HostMatcher::looks_like_own_asset_path( 'https://tracker.example/t.js?from=/wp-content/x' ) );
		self::assertFalse( HostMatcher::looks_like_own_asset_path( 'https://wp-content.example/track.js' ) );
		self::assertFalse( HostMatcher::looks_like_own_asset_path( '' ) );
		self::assertFalse( HostMatcher::looks_like_own_asset_path( 'data:text/javascript,alert(1)' ) );
	}

	/**
	 * The authority-confusion rules apply here too: the path is read from the
	 * URL a browser would request, not from what parse_url() alone reports.
	 * 'https://evil.example\\@own.test/wp-content/x.js' connects to
	 * evil.example, and the path exemption must not be the way it gets in
	 * under a different guise.
	 */
	public function test_own_asset_path_uses_browser_authority_rules(): void {
		$matcher = new HostMatcher( array( 'example.test' ) );

		// Still FOREIGN by host — the exemption is a separate, additive check,
		// and the rules combine it with classify(), never replace it.
		self::assertSame(
			HostMatcher::FOREIGN,
			$matcher->classify( 'https://evil.example\\@example.test/wp-content/x.js' )
		);
		// Irregular authority slashes must not hide the path from this check.
		self::assertTrue( HostMatcher::looks_like_own_asset_path( 'https:/\\/cdn.example.net/wp-content/x.js' ) );
		self::assertTrue( HostMatcher::looks_like_own_asset_path( "https://cdn.example.net\t/wp-includes/x.js" ) );
	}

	/**
	 * The owner's explicit instruction beats the heuristic. Without this,
	 * typing a host into "Always gate these hosts" would silently do nothing
	 * for anything that host serves under /wp-content/.
	 */
	public function test_always_gate_beats_the_asset_path_exemption(): void {
		$url = 'https://cdn.example.net/wp-content/plugins/tracker/t.js';

		$default = new HostMatcher( array( 'example.test' ) );
		self::assertTrue( $default->is_exempt_own_asset( $url ) );

		$forced = new HostMatcher( array( 'example.test' ), true, null, array( 'cdn.example.net' ) );
		self::assertFalse( $forced->is_exempt_own_asset( $url ) );

		// Wildcards work here exactly as they do everywhere else.
		$wild = new HostMatcher( array( 'example.test' ), true, null, array( '*.example.net' ) );
		self::assertFalse( $wild->is_exempt_own_asset( $url ) );

		// A different host on the list leaves the exemption alone.
		$other = new HostMatcher( array( 'example.test' ), true, null, array( 'other.example' ) );
		self::assertTrue( $other->is_exempt_own_asset( $url ) );
	}

	public function test_is_own_filter_can_veto_and_approve(): void {
		$matcher = new HostMatcher(
			array( 'example.test' ),
			true,
			static function ( bool $own, string $host ): bool {
				return 'trusted.example' === $host ? true : $own;
			}
		);

		self::assertSame( HostMatcher::OWN, $matcher->classify( 'https://trusted.example/frame' ) );
		self::assertSame( HostMatcher::FOREIGN, $matcher->classify( 'https://other.example/frame' ) );
	}

	/**
	 * Never gating the plugin's own script, across the URL shapes a CDN
	 * actually produces.
	 *
	 * This began life as a scheme-stripped string prefix inside a closure in
	 * Plugin.php, where no test could reach it — PipelineFactory never passes
	 * a should_gate callback — and two ordinary shapes defeated it. Both
	 * re-open the defect it exists to prevent: the plugin gates its own
	 * gate.js, and every placeholder becomes a button that does nothing.
	 */
	public function test_own_asset_base_survives_the_shapes_a_cdn_produces(): void {
		$base = 'https://cdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/';

		// Several CDN plugins emit protocol-relative URLs from plugins_url().
		self::assertTrue( HostMatcher::url_is_under( $base, '//cdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/js/gate.js' ) );
		// Hostnames are case-insensitive; the old prefix compare was not.
		self::assertTrue( HostMatcher::url_is_under( $base, 'https://CDN.EXAMPLE.NET/wp-content/plugins/calucon-third-party-embed-gate/assets/js/gate.js' ) );
		// The ordinary shape, with the ?ver= wp_enqueue_script always appends.
		self::assertTrue( HostMatcher::url_is_under( $base, 'https://cdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/js/gate.js?ver=0.13.0' ) );
		// A protocol-relative BASE, for a filter that returns one.
		self::assertTrue( HostMatcher::url_is_under( '//cdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/', 'https://cdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/js/gate.js' ) );
	}

	/**
	 * And the direction that must never widen: this is an exemption from
	 * gating, so a near miss has to fail closed.
	 */
	public function test_own_asset_base_does_not_exempt_a_near_miss(): void {
		$base = 'https://cdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/';

		// The base appearing later in the URL is not the base.
		self::assertFalse( HostMatcher::url_is_under( $base, 'https://evil.test/?u=cdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/x.js' ) );
		// Same host, another plugin's directory.
		self::assertFalse( HostMatcher::url_is_under( $base, 'https://cdn.example.net/wp-content/plugins/other/app.js' ) );
		// Same path, another host.
		self::assertFalse( HostMatcher::url_is_under( $base, 'https://evil.example/wp-content/plugins/calucon-third-party-embed-gate/assets/js/gate.js' ) );
		// A host that merely ends with ours.
		self::assertFalse( HostMatcher::url_is_under( $base, 'https://notcdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/js/gate.js' ) );
		// Authority confusion: parses as our host in PHP, connects elsewhere.
		self::assertFalse( HostMatcher::url_is_under( $base, 'https://evil.example\\@cdn.example.net/wp-content/plugins/calucon-third-party-embed-gate/assets/js/gate.js' ) );
		// Nothing to compare against.
		self::assertFalse( HostMatcher::url_is_under( '', 'https://cdn.example.net/x.js' ) );
		self::assertFalse( HostMatcher::url_is_under( $base, '' ) );
		self::assertFalse( HostMatcher::url_is_under( $base, 'data:text/javascript,alert(1)' ) );
	}

}
