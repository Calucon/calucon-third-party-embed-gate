<?php
/**
 * WP-CLI commands: the Status screen's answers, machine-readable.
 *
 * Read-only by design — everything here inspects and reports; changing
 * configuration stays with the settings screen and `wp option`. The scan
 * makes no outbound request (invariant 9): it renders content in memory
 * and reads markup, exactly like the admin Status screen.
 *
 * The primary consumer is whoever cannot click through wp-admin: CI jobs,
 * shell users, and AI agents that need to verify their own customisations
 * ("is this embed actually gated?") after editing functions.php.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Cli;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Support\Reports;

/**
 * Inspect Calucon Third-Party Embed Gate: which embeds appear in content and whether each is
 * gated, and which providers are configured.
 */
final class Commands {

	/** @var callable Returns the provider descriptors (builtins + filtered). */
	private $providers_source;

	/** @var callable Returns the ContentScan behind the Status screen. */
	private $scanner_source;

	/** @var callable Renders content through the_content with gating
	 *                suspended — the scanner must see original markup, and
	 *                unlike wp-admin, CLI is a context the gate runs in. */
	private $render_ungated;

	/**
	 * @param callable $providers_source fn(): array[].
	 * @param callable $scanner_source   fn(): ContentScan.
	 * @param callable $render_ungated   fn( string $content ): string.
	 */
	public function __construct( callable $providers_source, callable $scanner_source, callable $render_ungated ) {
		$this->providers_source = $providers_source;
		$this->scanner_source   = $scanner_source;
		$this->render_ungated   = $render_ungated;
	}

	/**
	 * Scans recent published content and reports every embed found and
	 * whether it is gated. Read-only; no outbound requests.
	 *
	 * Renders the latest posts and pages through the_content in memory —
	 * the same pipeline the front end uses — and classifies every iframe,
	 * embed/object, script and image it finds. Statuses: gated, own-host,
	 * no-usable-url, rule-disabled, provider-disabled. Anything other than
	 * `gated` on a third-party host is a row worth reading: it loads
	 * without consent, because of a setting that says so.
	 *
	 * Widgets, template parts and builder-rendered layouts are outside
	 * this scan, exactly as on the Status screen.
	 *
	 * ## OPTIONS
	 *
	 * [--posts=<number>]
	 * : How many of the most recent published posts and pages to render.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Human-readable overview.
	 *     wp calucon-embed-gate scan
	 *
	 *     # Machine-readable, for CI or an agent verifying its changes.
	 *     wp calucon-embed-gate scan --format=json
	 *
	 * @subcommand scan
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function scan( $args, $assoc_args ) {
		$limit = isset( $assoc_args['posts'] ) ? (int) $assoc_args['posts'] : Reports::SCAN_DEFAULT;

		\WP_CLI\Utils\format_items(
			isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table',
			Reports::scan( call_user_func( $this->scanner_source ), $this->render_ungated, $limit ),
			Reports::SCAN_FIELDS
		);
	}

	/**
	 * Lists the configured providers: builtins, theme/plugin registrations
	 * via the calucon_embed_gate_providers filter, and the owner's per-provider
	 * overrides, exactly as the gate resolves them.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp calucon-embed-gate providers --format=json
	 *
	 * @subcommand providers
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function providers( $args, $assoc_args ) {
		\WP_CLI\Utils\format_items(
			isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table',
			Reports::providers( (array) call_user_func( $this->providers_source ) ),
			Reports::PROVIDER_FIELDS
		);
	}
}
