<?php
/**
 * Cache-plugin flushing (PLAN.md §9.12): after reconfiguring the plugin,
 * every cached page still serves the old markup. Flush the caches we can
 * reach when settings are saved.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Best-effort purge of the known cache plugins. Every call is guarded; a
 * missing plugin is a no-op.
 */
final class CacheFlush {

	/**
	 * @return void
	 */
	public static function flush_all(): void {
		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		// LiteSpeed Cache.
		do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own purge hook; invoking it is the point.
		// Autoptimize.
		if ( class_exists( '\autoptimizeCache' ) && method_exists( '\autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}
		// SiteGround Optimizer.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		// Cloudflare (official plugin) listens for this.
		do_action( 'cloudflare_purge_everything' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- the Cloudflare plugin's own purge hook.
	}
}
