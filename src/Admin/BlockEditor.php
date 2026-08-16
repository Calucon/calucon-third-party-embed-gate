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
			CALUCON_EMBED_GATE_VERSION,
			true
		);
		wp_set_script_translations( 'calucon-embed-gate-editor', 'calucon-third-party-embed-gate' );
		wp_enqueue_style(
			'calucon-embed-gate-editor',
			plugins_url( 'assets/css/editor.css', CALUCON_EMBED_GATE_FILE ),
			array(),
			CALUCON_EMBED_GATE_VERSION
		);
	}

	/**
	 * @return void
	 */
	public function register_blocks(): void {
		$withdraw = array(
			'api_version'     => 2,
			'attributes'      => array(
				'label' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'render_callback' => array( $this, 'render_withdraw' ),
		);

		register_block_type( 'calucon-embed-gate/withdraw', $withdraw );
		// Back-compat: the pre-rename block name is stored in published post
		// content as <!-- wp:consent-gate/withdraw --> and must keep
		// rendering. Remove no earlier than 1.0.0, in a release of its own.
		register_block_type( 'consent-gate/withdraw', $withdraw );
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
