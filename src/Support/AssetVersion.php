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
	 * The same, for a path relative to the plugin directory.
	 *
	 * @param string $relative e.g. 'assets/js/gate.js'.
	 * @return string
	 */
	public static function of( string $relative ): string {
		return self::for_file(
			CALUCON_EMBED_GATE_DIR . '/' . ltrim( $relative, '/' ),
			CALUCON_EMBED_GATE_VERSION
		);
	}
}
