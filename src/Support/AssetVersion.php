<?php
/**
 * Cache-busting version for a bundled asset file.
 *
 * WordPress caches an asset by its URL, and the URL only changes when the
 * `ver` query argument does. The plugin version alone is not enough: two
 * different builds of the same version — a test build, a hotfix re-uploaded
 * over the same release — serve different bytes at an identical URL, and a
 * browser that already has the file is right to keep it. Anyone testing
 * successive builds then sees stale CSS and JS until they clear their cache.
 *
 * Appending the file's own modification time keeps the release number
 * readable in the URL while making the key follow the bytes.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the `ver` string for an enqueued file.
 */
final class AssetVersion {

	/**
	 * Version for one file: the release, plus its modification time when the
	 * file can be read. Falls back to the release alone — an unreadable file
	 * is somebody else's problem, and a missing cache key would be worse
	 * than a coarse one.
	 *
	 * @param string $path    Absolute path to the asset.
	 * @param string $release Plugin version.
	 * @return string
	 */
	public static function for_file( string $path, string $release ): string {
		$mtime = is_readable( $path ) ? filemtime( $path ) : false;

		return false === $mtime ? $release : $release . '.' . (string) $mtime;
	}

	/**
	 * The version to enqueue a bundled file with: the release on a live site,
	 * the release plus the file's mtime while developing.
	 *
	 * @param string $relative e.g. 'assets/js/gate.js'.
	 * @return string
	 */
	public static function of( string $relative ): string {
		$version = self::is_development()
			? self::for_file( CALUCON_EMBED_GATE_DIR . '/' . ltrim( $relative, '/' ), CALUCON_EMBED_GATE_VERSION )
			: CALUCON_EMBED_GATE_VERSION;

		/**
		 * Filter the cache-busting version of one bundled asset.
		 *
		 * The last word for anyone the built-in rule does not suit — a
		 * multi-server site wanting a build hash rather than an mtime, say,
		 * which is stable across machines in a way a timestamp is not.
		 *
		 * @param string $version  Version string for the `ver` argument.
		 * @param string $relative Asset path relative to the plugin, e.g. 'assets/js/gate.js'.
		 */
		return (string) apply_filters( 'calucon_embed_gate_asset_version', $version, $relative );
	}

	/**
	 * Is this a site where the same version gets rebuilt? Generous on
	 * purpose: a site that has said it is not production in ANY of the ways
	 * WordPress offers would rather have a correct cache key than a tidy URL.
	 *
	 * @return bool
	 */
	private static function is_development(): bool {
		// An explicit choice for THIS plugin wins over anything ambient, in
		// both directions: a live site used to test builds can say so without
		// having to claim it is not production, and a development site that
		// wants stable URLs can say that too.
		if ( defined( 'CALUCON_EMBED_GATE_DEV_ASSETS' ) ) {
			return (bool) CALUCON_EMBED_GATE_DEV_ASSETS;
		}
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return true;
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return 'production' !== wp_get_environment_type();
		}
		return false;
	}
}
