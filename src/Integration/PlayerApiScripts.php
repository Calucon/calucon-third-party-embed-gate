<?php
/**
 * A provider's player API that a builder enqueues for a background video.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Plugin;

/**
 * Holds back the player API script tags Beaver Builder enqueues.
 *
 * Beaver Builder's row background video is two things: an element with the
 * video's address, which BackgroundVideoRule removes, and the provider's
 * player API as an ordinary enqueued script —
 *
 *   <script id="youtube-player-js" src="https://www.youtube.com/iframe_api">
 *
 * printed in the footer, outside every content filter. With the element
 * gone the script has nothing to build, but it is still a request to the
 * provider on every page with such a row (measured on Beaver Builder Lite
 * 2.11: 28 to 31 requests before a click, the API and what it pulls in).
 *
 * Deliberately narrow: two handles, each only with the address Beaver
 * gives it. A theme that enqueues the same API under its own handle for
 * its own player is not touched here — whole-page gating reads those — and
 * a visitor-loaded embed does not need the API: it is an iframe.
 */
final class PlayerApiScripts {

	/** Handle => [ pattern of the address it is enqueued with, the embed URL that names its provider ]. */
	private const HELD = array(
		'youtube-player' => array( '#^(?:https?:)?//(?:www\.)?youtube\.com/(?:iframe_api|player_api)(?:[?\#]|$)#i', 'https://www.youtube.com/embed/0' ),
		'vimeo-player'   => array( '#^(?:https?:)?//player\.vimeo\.com/api/player\.js(?:[?\#]|$)#i', 'https://player.vimeo.com/video/0' ),
	);

	/** @var Plugin */
	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'script_loader_tag', array( $this, 'filter' ), 10, 3 );
	}

	/**
	 * @param string $tag    The script tag.
	 * @param string $handle Its handle.
	 * @param string $src    Its address.
	 * @return string
	 */
	public function filter( $tag, $handle, $src ): string {
		$tag = (string) $tag;
		if ( ! is_string( $handle ) || ! isset( self::HELD[ $handle ] ) || ! is_string( $src )
			|| ! preg_match( self::HELD[ $handle ][0], $src ) ) {
			return $tag;
		}
		if ( $this->plugin->should_bail() || ! $this->plugin->gates_url( self::HELD[ $handle ][1], array( 'integration' => 'script_loader' ) ) ) {
			return $tag;
		}
		return '';
	}
}
