<?php
/**
 * Consent Gate placeholder template.
 *
 * Copy this file to {your-theme}/consent-gate/placeholder.php to override
 * the panel markup. The structure below (PLAN.md §5.1) is the documented
 * minimum a custom template must keep for gate.js, themes and tests to
 * keep working:
 *
 *  - the outer element carries class="cg-embed", role="group", the
 *    aria-label, data-cg-provider and data-cg-payload;
 *  - a real <button type="button" class="cg-embed__button">;
 *  - a working fallback link (class="cg-embed__fallback" on its wrapper).
 *
 * Variables available:
 *
 * @var array  $provider       Provider descriptor (id, label, note, action, …).
 * @var array  $ctx            Integration context: post_id, block, integration.
 * @var string $aria_label     Accessible name for the panel.
 * @var string $note           Panel note text (plain text, escape it).
 * @var string $action         Button text (plain text, escape it).
 * @var string $fallback_url   Fallback link target (URL, escape it).
 * @var string $fallback_label Fallback link text (plain text, escape it).
 * @var string $payload_attr   data-cg-payload value, already HTML-escaped.
 * @var string $aspect         CSS aspect ratio ('16/9') or '' — reserve the
 *                             embed's space via the --cg-aspect custom
 *                             property so the page does not reflow (§5.3).
 *
 * The template runs outside WordPress in the fixture suite, so it uses
 * htmlspecialchars() rather than esc_attr()/esc_html(); both are correct
 * here and themes may use the WordPress functions instead.
 *
 * @package ConsentGate
 */

?>
<div class="cg-embed" role="group" aria-label="<?php echo htmlspecialchars( $aria_label, ENT_QUOTES, 'UTF-8' ); ?>" data-cg-provider="<?php echo htmlspecialchars( $provider['id'], ENT_QUOTES, 'UTF-8' ); ?>"<?php echo '' !== $aspect ? ' style="--cg-aspect:' . htmlspecialchars( $aspect, ENT_QUOTES, 'UTF-8' ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline. ?> data-cg-payload="<?php echo $payload_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the renderer. ?>"><div class="cg-embed__panel"><p class="cg-embed__note"><?php echo htmlspecialchars( $note, ENT_QUOTES, 'UTF-8' ); ?></p><button type="button" class="cg-embed__button"><?php echo htmlspecialchars( $action, ENT_QUOTES, 'UTF-8' ); ?></button><p class="cg-embed__fallback"><a href="<?php echo htmlspecialchars( $fallback_url, ENT_QUOTES, 'UTF-8' ); ?>" rel="noopener nofollow"><?php echo htmlspecialchars( $fallback_label, ENT_QUOTES, 'UTF-8' ); ?></a></p></div></div>
