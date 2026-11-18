<?php
/**
 * The plugin's two read-only reports as WordPress abilities.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Support\ContentScan;
use CaluconEmbedGate\Support\Reports;

/**
 * Registers `calucon-embed-gate/scan` and `calucon-embed-gate/providers`
 * with the Abilities API (WordPress 6.9+).
 *
 * An ability is a described, permission-checked function other software on
 * the site can discover and call — the REST API, the command palette, an
 * assistant the owner connected through the MCP adapter. These two are the
 * WP-CLI commands of the same names, for owners without a shell: "is every
 * embed on my site gated?" and "which providers does the gate know?". Both
 * print exactly what the commands print (Support\Reports).
 *
 * What they are not, and must not become:
 *
 * - Not a way to change anything. Both are read-only and annotated so; a
 *   write ability (let a host through, switch a provider off) would hand
 *   the one decision this plugin exists for to whatever is calling.
 * - Not public. `manage_options`, the capability of the settings screen
 *   the same information is on. The exposure flags are discovery hints,
 *   not a security boundary — the permission callback is.
 * - Not a request to anywhere. The scan renders the site's own content and
 *   reads the HTML (invariant 9).
 *
 * On a WordPress without the API the two hooks never fire and nothing
 * happens.
 */
final class Abilities {

	public const SCAN      = 'calucon-embed-gate/scan';
	public const PROVIDERS = 'calucon-embed-gate/providers';

	/** @var callable fn(): array[] — resolved provider descriptors. */
	private $providers_source;

	/** @var callable fn(): ContentScan */
	private $scanner_source;

	/** @var callable fn( string $content ): string — the_content without the gate. */
	private $render_ungated;

	/**
	 * @param callable $providers_source fn(): array[].
	 * @param callable $scanner_source   fn(): ContentScan.
	 * @param callable $render_ungated   fn( string ): string.
	 */
	public function __construct( callable $providers_source, callable $scanner_source, callable $render_ungated ) {
		$this->providers_source = $providers_source;
		$this->scanner_source   = $scanner_source;
		$this->render_ungated   = $render_ungated;
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * @return void
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$permission = static function (): bool {
			return current_user_can( 'manage_options' );
		};
		$meta       = array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			// Discoverable by the clients the owner has connected (REST,
			// the MCP adapter); still behind the permission callback.
			'public'       => true,
			'show_in_rest' => true,
		);
		$text = array( 'type' => 'string' );

		wp_register_ability(
			self::SCAN,
			array(
				'label'               => __( 'Scan content for third-party embeds', 'calucon-third-party-embed-gate' ),
				'description'         => __( 'Lists every embed found in the most recent published posts and pages — the host it would contact, the provider, and whether Calucon Third-Party Embed Gate gates it. Read-only; nothing is requested from any third party.', 'calucon-third-party-embed-gate' ),
				// Core's own category (6.9+): information about this site.
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'posts' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => Reports::SCAN_MAX,
							'default'     => Reports::SCAN_DEFAULT,
							'description' => __( 'How many of the most recent posts and pages to read.', 'calucon-third-party-embed-gate' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'host'       => $text,
							'tag'        => $text,
							'label'      => $text,
							'provider'   => $text,
							'status'     => $text,
							'count'      => array( 'type' => 'integer' ),
							'first_seen' => $text,
							'url'        => $text,
						),
						'additionalProperties' => false,
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();
					$limit = isset( $input['posts'] ) && is_numeric( $input['posts'] ) ? (int) $input['posts'] : Reports::SCAN_DEFAULT;
					$scan  = call_user_func( $this->scanner_source );
					return $scan instanceof ContentScan ? Reports::scan( $scan, $this->render_ungated, $limit ) : array();
				},
				'permission_callback' => $permission,
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			self::PROVIDERS,
			array(
				'label'               => __( 'List embed providers', 'calucon-third-party-embed-gate' ),
				'description'         => __( 'Lists the embed providers Calucon Third-Party Embed Gate knows on this site — built-in, added by code and added by the owner — with whether each is gated, the hosts it matches, the privacy-preserving host it loads from and its privacy policy. Read-only.', 'calucon-third-party-embed-gate' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'properties'           => array_fill_keys( Reports::PROVIDER_FIELDS, $text ),
						'additionalProperties' => false,
					),
				),
				'execute_callback'    => function (): array {
					return Reports::providers( (array) call_user_func( $this->providers_source ) );
				},
				'permission_callback' => $permission,
				'meta'                => $meta,
			)
		);
	}
}
