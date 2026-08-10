<?php
/**
 * Term, archive and author description integration (PLAN.md §3.3).
 *
 * Core never runs the_content on these, and admins (who have
 * unfiltered_html) do paste map iframes into category descriptions. On
 * block themes the core/term-description and core/post-author-biography
 * blocks pass through render_block; on classic themes these filters are
 * the only route.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

use ConsentGate\Plugin;

/**
 * Hooks term_description, get_the_archive_description and
 * get_the_author_description.
 */
final class Descriptions {

	/** @var Plugin */
	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'term_description', array( $this, 'filter' ), 20 );
		add_filter( 'get_the_archive_description', array( $this, 'filter' ), 20 );
		add_filter( 'get_the_author_description', array( $this, 'filter' ), 20 );
	}

	/**
	 * @param string $content Description HTML.
	 * @return string
	 */
	public function filter( $content ): string {
		$content = (string) $content;

		if ( ! Plugin::has_gateable_markup( $content ) || $this->plugin->should_bail() ) {
			return $content;
		}

		return $this->plugin->gate(
			$content,
			array(
				'integration' => 'description',
				'block'       => null,
				'post_id'     => null,
			)
		);
	}
}
