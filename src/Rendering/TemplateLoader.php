<?php
/**
 * Theme template override (PLAN.md §7.4).
 *
 * WordPress-free: the locator (locate_template in production) is injected.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Rendering;

/**
 * Resolves and renders {theme}/consent-gate/placeholder.php when a theme
 * ships one. The variables passed to the template are documented at the top
 * of the plugin's own templates/placeholder.php, and the §5.1 markup
 * contract is the documented minimum a custom template must satisfy.
 */
final class TemplateLoader {

	/** @var callable Returns an absolute template path or '' when none. */
	private $locator;

	/**
	 * @param callable|null $locator fn( string $relative ): string — maps
	 *                               'consent-gate/placeholder.php' to a theme
	 *                               file, '' when the theme has none.
	 */
	public function __construct( ?callable $locator = null ) {
		$this->locator = $locator ?? static function (): string {
			return '';
		};
	}

	/**
	 * @return string Absolute path of the theme override, '' when none.
	 */
	public function placeholder_template(): string {
		$path = (string) call_user_func( $this->locator, 'consent-gate/placeholder.php' );
		return ( '' !== $path && is_file( $path ) ) ? $path : '';
	}

	/**
	 * Render a template file with explicit variables, captured.
	 *
	 * @param string $template_file Absolute path.
	 * @param array  $vars          Variables exposed to the template.
	 * @return string Rendered HTML ('' on template error — the caller falls
	 *                back to the built-in markup, never to nothing).
	 */
	public function render( string $template_file, array $vars ): string {
		try {
			ob_start();
			( static function ( string $cg_template_file, array $cg_template_vars ): void {
				extract( $cg_template_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- explicit, documented template variables.
				include $cg_template_file;
			} )( $template_file, $vars );
			return (string) ob_get_clean();
		} catch ( \Throwable $e ) {
			ob_end_clean();
			return '';
		}
	}
}
