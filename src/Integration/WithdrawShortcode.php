<?php
/**
 * Withdrawal control (PLAN.md §6.2): storing consent creates an Art. 7(3)
 * withdrawal obligation that the stateless default does not have. This
 * shortcode gives the site a visible, keyboard-reachable control to place
 * in the privacy policy.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Integration;

/**
 * [consent_gate_withdraw] — renders a real button; gate.js clears the
 * plugin's storage key when it is pressed and announces the result via the
 * companion live region.
 */
final class WithdrawShortcode {

	/**
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'consent_gate_withdraw', array( $this, 'render' ) );
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'label' => __( 'Withdraw embed consents', 'consent-gate' ),
			),
			is_array( $atts ) ? $atts : array(),
			'consent_gate_withdraw'
		);

		$status_id = 'cg-withdraw-status-' . wp_unique_id();

		return '<button type="button" class="cg-withdraw" data-cg-withdraw aria-controls="' . esc_attr( $status_id ) . '">'
			. esc_html( $atts['label'] )
			. '</button>'
			. '<span id="' . esc_attr( $status_id ) . '" class="cg-withdraw__status" role="status" aria-live="polite"></span>';
	}
}
