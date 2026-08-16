<?php
/**
 * Comment integration (PLAN.md §3.3): comments can carry embeds too.
 *
 * On block themes the core/comments block routes comment output through
 * render_block, which is already covered. On classic themes comments.php
 * echoes comment_text() directly, and an iframe posted by a user with
 * unfiltered_html — or injected by an oEmbed-in-comments plugin hooking
 * WP_Embed::autoembed onto comment_text — loads ungated without this hook.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Plugin;

/**
 * Hooks comment_text late, after core formatting and autoembed-style
 * plugins have produced their markup.
 */
final class Comments {

	/** @var Plugin */
	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'comment_text', array( $this, 'filter' ), 50 );
	}

	/**
	 * @param string $content Comment HTML.
	 * @return string
	 */
	public function filter( $content ): string {
		$content = (string) $content;

		if ( ! $this->plugin->has_gateable_markup( $content ) || $this->plugin->should_bail() ) {
			return $content;
		}

		return $this->plugin->gate(
			$content,
			array(
				'integration' => 'comment',
				'block'       => null,
				'post_id'     => get_the_ID(),
			)
		);
	}
}
