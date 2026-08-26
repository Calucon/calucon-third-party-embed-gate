<?php
/**
 * Block editor integration (PLAN.md §7.5): the per-block "Gate this embed"
 * override and the withdrawal-control block. The editor script registers
 * the attribute and inspector control; this class registers the assets and
 * the dynamic block. No build step — plain JS against the wp.* globals.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Integration\WithdrawShortcode;
use CaluconEmbedGate\Support\AssetVersion;

/**
 * Registers editor assets and the calucon-embed-gate/withdraw dynamic block.
 */
final class BlockEditor {

	/** @var WithdrawShortcode Shared renderer: block and shortcode emit
	 *                         identical markup (invariant 2: server-side). */
	private WithdrawShortcode $withdraw;

	public function __construct( WithdrawShortcode $withdraw ) {
		$this->withdraw = $withdraw;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_script(
			'calucon-embed-gate-editor',
			plugins_url( 'assets/js/editor.js', CALUCON_EMBED_GATE_FILE ),
			array( 'wp-hooks', 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-i18n' ),
			AssetVersion::of( 'assets/js/editor.js' ),
			true
		);
		// The third argument is not optional for a plugin that ships its own
		// JSON: without a path, wp_set_script_translations() looks only in
		// WP_LANG_DIR/plugins, where a bundled file never lives. Recent
		// WordPress finds ours anyway through the textdomain registry, which is
		// why this was invisible — but on 5.9 to 6.6, which this plugin
		// supports, the block editor silently stayed English while the front end
		// and the settings screen were translated. A language pack in
		// WP_LANG_DIR still takes precedence over the path given here.
		wp_set_script_translations(
			'calucon-embed-gate-editor',
			'calucon-third-party-embed-gate',
			CALUCON_EMBED_GATE_DIR . '/languages'
		);
		wp_enqueue_style(
			'calucon-embed-gate-editor',
			plugins_url( 'assets/css/editor.css', CALUCON_EMBED_GATE_FILE ),
			array(),
			AssetVersion::of( 'assets/css/editor.css' )
		);
	}

	/**
	 * @return void
	 */
	public function register_blocks(): void {
		register_block_type(
			'calucon-embed-gate/withdraw',
			array(
				'api_version'     => 2,
				'attributes'      => array(
					'label' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'render_callback' => array( $this, 'render_withdraw' ),
			)
		);
	}

	/**
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_withdraw( $attributes ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$atts       = array();
		if ( isset( $attributes['label'] ) && is_string( $attributes['label'] ) && '' !== trim( $attributes['label'] ) ) {
			$atts['label'] = trim( $attributes['label'] );
		}
		return $this->withdraw->render( $atts );
	}
}
