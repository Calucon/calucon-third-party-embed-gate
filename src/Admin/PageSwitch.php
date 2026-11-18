<?php
/**
 * The per-page switch: "Do not gate embeds on this page".
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One post meta, one meaning: on this post's own address, the plugin stands
 * down — for everything on the page, not only the post's content.
 *
 * Why a switch at all. A site sometimes has one page where an embed must be
 * there the moment the page opens — a kiosk screen, a landing page whose
 * visitors arrive through a consent step of the owner's own. Until 1.1 the
 * ways to say so were the per-block "Never gate" (blocks only), the
 * never-gate host list (site-wide) or a filter in functions.php. This is the
 * same decision as the per-block override, one level up, and it comes with
 * the same price: every visitor of that page contacts the third parties on
 * it without a click. The editor panel says that in as many words.
 *
 * Scope, deliberately narrow: the post's OWN address (is_singular()). In an
 * archive, a Query Loop, a feed or a REST response the same post's embeds
 * stay gated — the owner made a decision about a page, not about every
 * place its content travels to.
 *
 * It must never be invisible (invariant 6 is about exactly this: the
 * failure worth fearing is the one nobody sees). So the Detection tab lists
 * every page that has the switch on, with a link to edit it.
 *
 * Who may set it: whoever may edit that post — the same people who can
 * already set "Never gate" on each of its blocks.
 */
final class PageSwitch {

	/** The post meta key. Protected (leading underscore): never in the Custom Fields box. */
	public const META = '_calucon_embed_gate';

	/** The one value that means "off on this page"; anything else is the site default. */
	public const NEVER = 'never';

	private const NONCE_FIELD  = 'calucon_embed_gate_page_nonce';
	private const NONCE_ACTION = 'calucon_embed_gate_page_switch';
	private const FIELD        = 'calucon_embed_gate_page_switch';

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
	}

	/**
	 * Whether gating is switched off on this post's own address.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_off( int $post_id ): bool {
		return $post_id > 0 && self::NEVER === get_post_meta( $post_id, self::META, true );
	}

	/**
	 * @param mixed $value Raw meta value.
	 * @return string 'never' or ''.
	 */
	public static function sanitize( $value ): string {
		return self::NEVER === $value ? self::NEVER : '';
	}

	/**
	 * The meta, for every post type, readable and writable through the REST
	 * API by whoever can edit the post — which is what the block editor's
	 * panel uses. (WordPress exposes post meta in REST only for post types
	 * that support custom fields; the meta box below covers the rest.)
	 *
	 * @return void
	 */
	public function register_meta(): void {
		register_post_meta(
			'',
			self::META,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $post_id ): bool {
					return current_user_can( 'edit_post', (int) $post_id );
				},
			)
		);
	}

	/**
	 * The classic editor's version of the control — and the block editor's,
	 * for a post type whose meta the REST API does not carry.
	 *
	 * @param string $post_type Post type of the screen.
	 * @return void
	 */
	public function add_meta_box( $post_type ): void {
		$type = is_string( $post_type ) ? get_post_type_object( $post_type ) : null;
		if ( null === $type || ! $type->public ) {
			return;
		}
		add_meta_box(
			'calucon-embed-gate-page',
			__( 'Calucon Third-Party Embed Gate', 'calucon-third-party-embed-gate' ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'side',
			'default',
			// Where the block editor shows its own panel, this box would be
			// the same control twice.
			array( '__back_compat_meta_box' => post_type_supports( $post_type, 'custom-fields' ) )
		);
	}

	/**
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	public function render_meta_box( $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>" value="<?php echo esc_attr( self::NEVER ); ?>" <?php checked( self::is_off( (int) $post->ID ) ); ?>>
				<?php esc_html_e( 'Do not gate embeds on this page', 'calucon-third-party-embed-gate' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'When this is on, every embed on this page loads as soon as the page opens — for every visitor, without a click. It applies to this page’s own address only: in lists, feeds and search results its embeds stay gated.', 'calucon-third-party-embed-gate' ); ?>
		</p>
		<?php
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function save_meta_box( $post_id, $post ): void {
		// Only a save that came from this box: the block editor saves through
		// REST and never sends the field, and its absence must not be read as
		// "unchecked".
		if ( ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}
		$off = isset( $_POST[ self::FIELD ] ) && self::NEVER === sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) );
		if ( $off ) {
			update_post_meta( (int) $post_id, self::META, self::NEVER );
		} else {
			delete_post_meta( (int) $post_id, self::META );
		}
	}

	/**
	 * The pages that have the switch on, newest change first — for the
	 * Detection tab, so the decision is never out of sight.
	 *
	 * @param int $limit Upper bound on rows.
	 * @return \WP_Post[]
	 */
	public static function pages_switched_off( int $limit = 50 ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => 'any',
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => max( 1, min( 200, $limit ) ),
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one indexed key, on the settings screen only.
				'meta_key'               => self::META,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
				'meta_value'             => self::NEVER,
			)
		);
		return $query->posts;
	}
}
