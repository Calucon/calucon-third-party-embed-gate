<?php
/**
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Tests\Unit;

use CaluconEmbedGate\Providers\Provider;
use CaluconEmbedGate\Rendering\PlaceholderRenderer;
use CaluconEmbedGate\Rendering\TemplateLoader;
use PHPUnit\Framework\TestCase;

final class TemplateLoaderTest extends TestCase {

	private function renderer_with_template( string $template_file ): PlaceholderRenderer {
		return new PlaceholderRenderer(
			null,
			null,
			null,
			new TemplateLoader(
				static function () use ( $template_file ): string {
					return $template_file;
				}
			)
		);
	}

	private function provider(): array {
		return Provider::normalize(
			array(
				'id'       => 'generic',
				'label'    => 'example.org',
				'note'     => 'Note.',
				'action'   => 'Load',
				'fallback' => 'https://example.org/x',
			)
		);
	}

	public function test_shipped_template_matches_the_builtin_markup(): void {
		// The reference template must stay in sync with the §5.1 contract:
		// rendering through it yields the same markup as the built-in path.
		$provider = $this->provider();
		$attrs    = array( 'title' => 'T', 'width' => '400' );
		$src      = 'https://example.org/embed/1';

		$builtin   = ( new PlaceholderRenderer() )->render( $provider, $src, $attrs );
		$templated = $this->renderer_with_template( dirname( __DIR__, 2 ) . '/templates/placeholder.php' )
			->render( $provider, $src, $attrs );

		self::assertSame( $builtin, trim( $templated ) );
	}

	public function test_theme_override_is_used(): void {
		$template = tempnam( sys_get_temp_dir(), 'cg' );
		file_put_contents(
			$template,
			'<div class="cg-embed custom" role="group" aria-label="<?php echo htmlspecialchars( $aria_label, ENT_QUOTES ); ?>" data-cg-provider="<?php echo $provider["id"]; ?>"><?php echo $payload_tag; ?><button type="button" class="cg-embed__button">GO</button><p class="cg-embed__fallback"><a href="<?php echo htmlspecialchars( $fallback_url, ENT_QUOTES ); ?>">link</a></p></div>'
		);

		$html = $this->renderer_with_template( $template )->render( $this->provider(), 'https://example.org/embed/1', array() );
		unlink( $template );

		self::assertStringContainsString( 'class="cg-embed custom"', $html );
		self::assertStringContainsString( '>GO</button>', $html );
		self::assertStringContainsString( '<script type="application/json" class="cg-embed__payload">', $html );
	}

	public function test_broken_template_falls_back_to_builtin_markup(): void {
		$template = tempnam( sys_get_temp_dir(), 'cg' );
		file_put_contents( $template, '<?php throw new \RuntimeException( "broken template" );' );

		$html = $this->renderer_with_template( $template )->render( $this->provider(), 'https://example.org/embed/1', array() );
		unlink( $template );

		// Never nothing: the visitor still gets the working built-in panel.
		self::assertStringContainsString( '<button type="button" class="cg-embed__button">', $html );
		self::assertStringContainsString( 'role="group"', $html );
	}
}
