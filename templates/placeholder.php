<?php
/**
 * Calucon Third-Party Embed Gate placeholder template.
 *
 * Copy this file to {your-theme}/calucon-embed-gate/placeholder.php to override
 * the panel markup. The structure below (PLAN.md §5.1) is the documented
 * minimum a custom template must keep for gate.js, themes and tests to
 * keep working:
 *
 *  - the outer element carries class="cg-embed", role="group", the
 *    aria-label, data-cg-provider and data-cg-host;
 *  - $payload_tag echoed RAW as the outer element's first child — see
 *    below; this is the one line a template must not reinterpret;
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
 * @var string $payload_tag    The payload element, complete:
 *                             <script type="application/json"
 *                             class="cg-embed__payload">{json}</script>.
 *                             Echo it as is, as a direct child of the
 *                             container. It is the front end's security
 *                             boundary: gate.js executes only payloads it
 *                             finds in this element, because WordPress's
 *                             kses lets any author write class and data-*
 *                             attributes but never a <script>. A template
 *                             that moves the payload into an attribute
 *                             reopens a Contributor-level XSS.
 * @var string $aspect         CSS aspect ratio ('16/9') or '' — reserve the
 *                             embed's space via the --cg-aspect custom
 *                             property so the page does not reflow (§5.3).
 * @var string $host           Host the embed will contact ('' when unknown);
 *                             carried as data-cg-host so activation and
 *                             consent memory can scope generic embeds.
 * @var string $privacy_url    Provider privacy-policy URL ('' when unknown,
 *                             or when the site disabled the link). Optional
 *                             to render, but sites that keep it inform
 *                             visitors before any click.
 * @var string $privacy_label  Link text for the privacy link (plain text,
 *                             escape it).
 * @var bool   $show_button    False when the panel sits inside <noscript>,
 *                             where scripting is off by definition and a
 *                             button could never do anything (§8). Render the
 *                             note and the fallback link, not the button.
 * @var string $poster         Site-origin poster image URL ('' for none) —
 *                             the §5.4 owner-supplied poster. If you render
 *                             it, keep alt="" aria-hidden="true" (the group
 *                             label already names the embed), keep the
 *                             cg-embed--poster class on the container and
 *                             cg-embed__poster on the image so gate.js can
 *                             remove it on activation, and NEVER substitute
 *                             a provider-hosted image URL.
 *
 * The template runs outside WordPress in the fixture suite, so it uses
 * htmlspecialchars() rather than esc_attr()/esc_html(); both are correct
 * here and themes may use the WordPress functions instead.
 *
 * @package CaluconEmbedGate
 */

// Direct web access: render nothing. The test harness defines ABSPATH as a
// sentinel (tests/bootstrap.php) so the fixture suite still runs the template
// without booting WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Included through the renderer but without its variables (belt-and-braces):
// render nothing rather than emit a broken panel.
if ( ! isset( $provider, $aria_label, $note, $action, $fallback_url, $fallback_label, $payload_tag, $aspect ) ) {
	return;
}
?>
<div class="cg-embed<?php echo isset( $poster ) && is_string( $poster ) && '' !== $poster ? ' cg-embed--poster' : ''; ?>" role="group" aria-label="<?php echo htmlspecialchars( $aria_label, ENT_QUOTES, 'UTF-8' ); ?>" data-cg-provider="<?php echo htmlspecialchars( $provider['id'], ENT_QUOTES, 'UTF-8' ); ?>"<?php echo isset( $host ) && is_string( $host ) && '' !== $host ? ' data-cg-host="' . htmlspecialchars( $host, ENT_QUOTES, 'UTF-8' ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline. ?><?php echo '' !== $aspect ? ' style="--cg-aspect:' . htmlspecialchars( $aspect, ENT_QUOTES, 'UTF-8' ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline. ?>><?php echo $payload_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the JSON payload element, built and encoded by the renderer. ?><?php echo isset( $poster ) && is_string( $poster ) && '' !== $poster ? '<img class="cg-embed__poster" src="' . htmlspecialchars( $poster, ENT_QUOTES, 'UTF-8' ) . '" alt="" aria-hidden="true" loading="lazy">' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline. ?><div class="cg-embed__panel"><p class="cg-embed__note"><?php echo htmlspecialchars( $note, ENT_QUOTES, 'UTF-8' ); ?></p><?php echo ! isset( $show_button ) || $show_button ? '<button type="button" class="cg-embed__button">' . htmlspecialchars( $action, ENT_QUOTES, 'UTF-8' ) . '</button>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline. ?><p class="cg-embed__fallback"><a href="<?php echo htmlspecialchars( $fallback_url, ENT_QUOTES, 'UTF-8' ); ?>" rel="noopener nofollow"><?php echo htmlspecialchars( $fallback_label, ENT_QUOTES, 'UTF-8' ); ?></a></p><?php echo isset( $privacy_url, $privacy_label ) && is_string( $privacy_url ) && '' !== $privacy_url ? '<p class="cg-embed__privacy"><a href="' . htmlspecialchars( $privacy_url, ENT_QUOTES, 'UTF-8' ) . '" rel="noopener nofollow">' . htmlspecialchars( (string) $privacy_label, ENT_QUOTES, 'UTF-8' ) . '</a></p>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline. ?></div></div>
