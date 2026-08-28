<?php
/**
 * Decides whether a URL points at the site itself or at a third party.
 *
 * WordPress-free by design (PLAN.md §2.2): the own-host list is injected by
 * the integration layer, which is where home_url()/site_url() live.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Detection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classifies embed URLs. The failure mode must always be "gated something
 * harmless", never "let a new tracker through" (PLAN.md §1, invariant 6) —
 * but URLs that cannot trigger a network request at all are never gated,
 * because a placeholder whose fallback link points nowhere is worse than
 * the original (PLAN.md §9.5).
 */
final class HostMatcher {

	/** URL cannot cause a third-party request; pass through, never rebuild. */
	public const SKIP = 'skip';

	/** Same-origin (or declared own host); pass through. */
	public const OWN = 'own';

	/** Cross-origin http(s); gate it. */
	public const FOREIGN = 'foreign';

	/** @var string[] Normalised exact own hosts. */
	private array $own_hosts = array();

	/** @var string[] Normalised wildcard suffixes, e.g. '.example.com'. */
	private array $wildcards = array();

	/** @var bool Treat www.example.com and example.com as the same site. */
	private bool $www_equivalence;

	/** @var callable|null Extra veto/approve hook: fn( bool $own, string $host ): bool */
	private $is_own_filter;

	/** @var string[] The owner's always-gate list; beats the asset-path exemption. */
	private array $always_gate = array();

	/**
	 * @param string[]      $own_hosts       Hosts that count as "ours". Entries
	 *                                       starting with '*.' match any subdomain.
	 * @param bool          $www_equivalence On by default (PLAN.md §3.4).
	 * @param callable|null $is_own_filter   Bridge for the calucon_embed_gate_is_own_host filter.
	 * @param string[]      $always_gate     Hosts the owner has said to gate whatever
	 *                                       else decides — consulted only by
	 *                                       is_exempt_own_asset(), because the
	 *                                       own-host veto already lives in the filter.
	 */
	public function __construct( array $own_hosts, bool $www_equivalence = true, ?callable $is_own_filter = null, array $always_gate = array() ) {
		$this->www_equivalence = $www_equivalence;
		$this->is_own_filter   = $is_own_filter;
		$this->always_gate     = $always_gate;

		foreach ( $own_hosts as $host ) {
			$host = trim( (string) $host );
			if ( '' === $host ) {
				continue;
			}
			if ( 0 === strpos( $host, '*.' ) ) {
				$this->wildcards[] = $this->normalize_host( substr( $host, 1 ) ); // Keep the leading dot.
			} else {
				$this->own_hosts[] = $this->normalize_host( $host );
			}
		}
	}

	/**
	 * Classify a URL as SKIP, OWN or FOREIGN.
	 *
	 * @param string $url Raw (already entity-decoded) URL from the markup.
	 * @return string One of the class constants.
	 */
	public function classify( string $url ): string {
		$url = self::preprocess( $url );
		if ( '' === $url ) {
			return self::SKIP;
		}

		// Schemes that never produce a third-party request: never gated,
		// never rebuilt (PLAN.md §3.4).
		if ( preg_match( '/^(data|blob|about|javascript):/i', $url ) ) {
			return self::SKIP;
		}

		if ( preg_match( '#^https?:#i', $url ) ) {
			// Collapse the scheme and any run of (already slash-normalised)
			// authority slashes into 'scheme://'. Browsers ignore extra,
			// missing or backslash authority slashes for special schemes, so
			// 'https:/\/evil.example' and 'https:evil.example' both name host evil.example
			// — parse_url() alone would miss both and let them through (§3.4).
			$url  = preg_replace( '#^(https?:)/*#i', '$1//', $url );
			$host = $this->extract_host( $url );
		} elseif ( preg_match( '#^/{2,}#', $url ) ) {
			// Protocol-relative: resolves against the page scheme, host decides.
			// Two-or-more leading slashes — including the '/\' open-redirect
			// shape, now '//' after slash normalisation — reach here.
			$host = $this->extract_host( 'https:' . preg_replace( '#^/+#', '//', $url ) );
		} elseif ( 0 === strpos( $url, '/' ) ) {
			// Single leading slash: a same-origin absolute path, never gated.
			return self::OWN;
		} elseif ( preg_match( '/^[a-z][a-z0-9+.-]*:/i', $url ) ) {
			// Unknown scheme (mailto:, tel:, …): an iframe will not fetch it.
			return self::SKIP;
		} else {
			// Relative URL: same-origin by definition, never gated.
			return self::OWN;
		}

		if ( null === $host || '' === $host ) {
			// Unparseable http(s) URL: the browser will not load it either,
			// and a placeholder would have no working fallback link.
			return self::SKIP;
		}

		return $this->is_own_host( $host ) ? self::OWN : self::FOREIGN;
	}

	/**
	 * Normalised host of an absolute or protocol-relative URL.
	 *
	 * @param string $url URL.
	 * @return string|null Null when the URL carries no host.
	 */
	public function host_of( string $url ) {
		$url = self::preprocess( $url );
		if ( '' === $url ) {
			return null;
		}
		if ( preg_match( '#^https?:#i', $url ) ) {
			$url = preg_replace( '#^(https?:)/*#i', '$1//', $url );
		} elseif ( preg_match( '#^/{2,}#', $url ) ) {
			$url = 'https:' . preg_replace( '#^/+#', '//', $url );
		}
		$host = $this->extract_host( $url );
		return null === $host ? null : $this->normalize_host( $host );
	}

	/**
	 * Browser-style URL preprocessing applied before parse_url(), so this
	 * class and the browser agree on the authority (invariant 6). Browsers
	 * strip ASCII tab/newline characters anywhere in a URL, and for the
	 * special schemes this plugin gates they treat a backslash as a forward
	 * slash. Without this, 'https://evil.example\@own.example/' parses to host
	 * 'own.example' in PHP but connects to 'evil.example' in every browser — a
	 * third party slipping past the gate.
	 *
	 * @param string $url Raw URL from the markup.
	 * @return string
	 */
	private static function preprocess( string $url ): string {
		$url = str_replace( array( "\t", "\n", "\r" ), '', $url );
		$url = str_replace( '\\', '/', $url );
		return trim( $url );
	}

	/**
	 * Is this URL inside the given asset base — same host, same path prefix?
	 *
	 * Used for one thing: never gating the plugin's own script. That check
	 * lived in Plugin.php as a scheme-stripped string prefix, which two
	 * ordinary shapes defeated — a protocol-relative URL (several CDN plugins
	 * emit those from plugins_url()) and an uppercased host. Either made the
	 * plugin gate its own gate.js again, and the failure is silent: every
	 * placeholder becomes a button that does nothing.
	 *
	 * So it lives here instead, beside the same authority normalisation the
	 * rest of this class uses, where it is also reachable from a unit test —
	 * the closure in Plugin.php was not, because PipelineFactory never passes
	 * one.
	 *
	 * Host compares case-insensitively (hostnames are), path prefix compares
	 * exactly (paths are not).
	 *
	 * @param string $base Absolute URL of the asset directory.
	 * @param string $url  URL from the markup.
	 * @return bool
	 */
	public static function url_is_under( string $base, string $url ): bool {
		$base_parts = self::authority_and_path( $base );
		$url_parts  = self::authority_and_path( $url );
		if ( null === $base_parts || null === $url_parts ) {
			return false;
		}
		if ( $base_parts[0] !== $url_parts[0] ) {
			return false;
		}
		return '' !== $base_parts[1] && 0 === strpos( $url_parts[1], $base_parts[1] );
	}

	/**
	 * Is this URL's PATH under the given base's path, whatever host serves it?
	 *
	 * The companion to url_is_under(), for the case that rule cannot see. A
	 * CDN that rewrites the finished HTML leaves plugins_url() reporting the
	 * origin host while the markup carries the CDN's — so a host comparison
	 * fails on exactly the setup the own-asset path rule exists for, and the
	 * plugin gates its own gate.js again.
	 *
	 * Host-blind on purpose, and safe because the base is this plugin's own
	 * asset directory: the path carries the plugin's slug, so a collision
	 * means somebody is deliberately serving a copy of our own script, and
	 * the worst outcome is that our own loader runs. Compare that with the
	 * alternative — the gate silently doing nothing on every page.
	 *
	 * That argument holds for scripts and for nothing else. "The worst
	 * outcome is our own loader runs" is false for an iframe at this path:
	 * there the worst outcome is a third-party frame with no panel and no
	 * link, which is invariant 6's invisible failure. Plugin::pipeline()
	 * consults this for ScriptRule only; never wire it into IframeRule,
	 * ImageRule or EmbedObjectRule.
	 *
	 * @param string $base Absolute URL of the asset directory.
	 * @param string $url  URL from the markup.
	 * @return bool
	 */
	public static function path_is_under( string $base, string $url ): bool {
		$base_parts = self::authority_and_path( $base );
		$url_parts  = self::authority_and_path( $url );
		if ( null === $base_parts || null === $url_parts || '' === $base_parts[1] ) {
			return false;
		}
		return 0 === strpos( $url_parts[1], $base_parts[1] );
	}

	/**
	 * Lowercased host and raw path of a URL, browser-normalised first.
	 *
	 * @param string $url URL.
	 * @return array{0:string,1:string}|null
	 */
	private static function authority_and_path( string $url ): ?array {
		$url = self::preprocess( $url );
		if ( '' === $url ) {
			return null;
		}
		if ( preg_match( '#^https?:#i', $url ) ) {
			$url = (string) preg_replace( '#^(https?:)/*#i', '$1//', $url );
		} elseif ( preg_match( '#^/{2,}#', $url ) ) {
			$url = 'https:' . (string) preg_replace( '#^/+#', '//', $url );
		} else {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress-free layer (PLAN.md §2.2).
		$host = parse_url( $url, PHP_URL_HOST );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress-free layer (PLAN.md §2.2).
		$path = parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $host ) || '' === $host ) {
			return null;
		}
		return array( strtolower( $host ), is_string( $path ) ? $path : '' );
	}

	/**
	 * Does this URL's path look like WordPress's own asset tree?
	 *
	 * The escape hatch for a CDN that rewrites the finished HTML rather than
	 * filtering WordPress's URL functions. Plugin::own_hosts() already trusts
	 * the hosts content_url()/includes_url()/… report, which covers every CDN
	 * that works through those filters; one that rewrites the output buffer
	 * instead is invisible to that, and with whole-page buffering on the
	 * site's own wp-includes scripts then look third-party and get gated —
	 * which breaks the site's JavaScript rather than protecting anyone.
	 *
	 * Callers should use is_exempt_own_asset(), which is this check plus the
	 * owner's always-gate list. This raw form exists so the shape of the URL
	 * can be tested on its own.
	 *
	 * **Only ScriptRule and StylesheetRule may consult this.** Never
	 * IframeRule, ImageRule or EmbedObjectRule: invariant 6 — an unknown
	 * third-party iframe is gated by default — has no exceptions, and a path
	 * heuristic is exactly the hole it exists to close. For a script or a
	 * stylesheet the trade is different: to abuse it a third party would have
	 * to serve from a /wp-content/ or /wp-includes/ path, which in practice
	 * means hosting a copy of somebody's WordPress, and the owner still has
	 * always_gate to override it.
	 *
	 * Substring, not prefix: WordPress in a subdirectory gives
	 * '/blog/wp-content/…', and CDNs routinely prefix a pull-zone path.
	 *
	 * @param string $url Raw (already entity-decoded) URL from the markup.
	 * @return bool
	 */
	public static function looks_like_own_asset_path( string $url ): bool {
		$url = self::preprocess( $url );
		if ( '' === $url ) {
			return false;
		}
		// Normalise the authority the way classify() does, so the path read
		// here is the path a browser would actually request (§3.4).
		if ( preg_match( '#^https?:#i', $url ) ) {
			$url = preg_replace( '#^(https?:)/*#i', '$1//', $url );
		} elseif ( preg_match( '#^/{2,}#', $url ) ) {
			$url = 'https:' . preg_replace( '#^/+#', '//', $url );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress-free layer (PLAN.md §2.2).
		$path = strtolower( (string) parse_url( (string) $url, PHP_URL_PATH ) );

		return false !== strpos( $path, '/wp-includes/' ) || false !== strpos( $path, '/wp-content/' );
	}

	/**
	 * Should this script/stylesheet URL be left alone as one of the site's
	 * own assets, wherever a CDN has moved it to?
	 *
	 * looks_like_own_asset_path() is a heuristic about the shape of a URL, so
	 * the owner's explicit instruction outranks it: a host on the always-gate
	 * list is gated even if its path looks like WordPress's own asset tree.
	 * Without this, typing a host into "Always gate these hosts" would
	 * silently do nothing for anything served under /wp-content/.
	 *
	 * @param string $url Raw (already entity-decoded) URL from the markup.
	 * @return bool
	 */
	public function is_exempt_own_asset( string $url ): bool {
		if ( ! self::looks_like_own_asset_path( $url ) ) {
			return false;
		}
		$host = $this->host_of( $url );
		if ( null === $host ) {
			return false;
		}
		return ! self::host_matches_list( $host, $this->always_gate );
	}

	/**
	 * Is this (already extracted) host one of ours?
	 *
	 * @param string $host Host name, possibly mixed case / IDN.
	 * @return bool
	 */
	public function is_own_host( string $host ): bool {
		$host = $this->normalize_host( $host );
		$own  = $this->matches_own( $host );

		if ( null !== $this->is_own_filter ) {
			$own = (bool) call_user_func( $this->is_own_filter, $own, $host );
		}

		return $own;
	}

	/**
	 * @param string $host Normalised host.
	 * @return bool
	 */
	private function matches_own( string $host ): bool {
		$variants = array( $host );
		if ( $this->www_equivalence ) {
			$variants[] = ( 0 === strpos( $host, 'www.' ) ) ? substr( $host, 4 ) : 'www.' . $host;
		}

		foreach ( $variants as $variant ) {
			if ( in_array( $variant, $this->own_hosts, true ) ) {
				return true;
			}
			foreach ( $this->wildcards as $suffix ) {
				// '*.example.com' matches sub.example.com and example.com itself.
				if ( substr( $variant, -strlen( $suffix ) ) === $suffix || substr( $suffix, 1 ) === $variant ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Does a host match a configured host list ('*.' wildcards allowed)?
	 * Shared by the always-gate setting, which must use the same matching
	 * rules as the own-host lists it overrides.
	 *
	 * @param string   $host Host name (any case).
	 * @param string[] $host_list Configured entries.
	 * @return bool
	 */
	public static function host_matches_list( string $host, array $host_list ): bool {
		$host = strtolower( rtrim( trim( $host ), '.' ) );
		foreach ( $host_list as $entry ) {
			if ( ! is_string( $entry ) || '' === $entry ) {
				continue;
			}
			$entry = strtolower( trim( $entry ) );
			if ( 0 === strpos( $entry, '*.' ) ) {
				$suffix = substr( $entry, 1 ); // Keep the leading dot.
				if ( substr( $host, -strlen( $suffix ) ) === $suffix || substr( $suffix, 1 ) === $host ) {
					return true;
				}
			} elseif ( $host === $entry ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Lowercase, strip trailing dot, and punycode IDNs so 'münchen.de'
	 * and 'xn--mnchen-3ya.de' compare equal (PLAN.md §3.4).
	 *
	 * @param string $host Raw host.
	 * @return string
	 */
	private function normalize_host( string $host ): string {
		$host = strtolower( rtrim( trim( $host ), '.' ) );

		if ( '' !== $host && function_exists( 'idn_to_ascii' ) && preg_match( '/[^\x00-\x7f]/', $host ) ) {
			$ascii = idn_to_ascii( $host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46 );
			if ( false !== $ascii && null !== $ascii ) {
				$host = $ascii;
			}
		}

		return $host;
	}

	/**
	 * @param string $url Absolute URL.
	 * @return string|null Host, or null when unparseable.
	 */
	private function extract_host( string $url ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress-free layer (PLAN.md §2.2); wp_parse_url() is unavailable in the no-WordPress fixture suite, and preprocess() already normalised the authority.
		$host = parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? $host : null;
	}
}
