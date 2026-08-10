<?php
/**
 * Builds the WordPress-free pipeline exactly as the plugin wires it,
 * minus the WordPress bridges. Shared by unit tests, fixture generation
 * and the E2E test server.
 *
 * @package ConsentGate
 */

namespace ConsentGate\Tests\Support;

use ConsentGate\Detection\HostMatcher;
use ConsentGate\Detection\HtmlScanner;
use ConsentGate\Detection\IframeRule;
use ConsentGate\Detection\ScriptRule;
use ConsentGate\Providers\Builtin\Descriptors;
use ConsentGate\Providers\Registry;
use ConsentGate\Rendering\PlaceholderRenderer;

final class PipelineFactory {

	/**
	 * Run content through both rules, as Plugin::gate() does. The fixture
	 * corpus treats example.test as the site's own host.
	 *
	 * @param string       $html      Content.
	 * @param string[]     $own_hosts Own hosts.
	 * @param array        $ctx       Context.
	 * @param array[]|null $providers Descriptor set; builtins by default.
	 * @return string
	 */
	public static function gate( string $html, array $own_hosts = array( 'example.test' ), array $ctx = array(), ?array $providers = null ): string {
		$scanner  = new HtmlScanner();
		$hosts    = new HostMatcher( $own_hosts );
		$registry = new Registry( null === $providers ? Descriptors::all() : $providers );
		$renderer = new PlaceholderRenderer();

		$iframe = new IframeRule( $scanner, $hosts, $registry, $renderer );
		$script = new ScriptRule( $scanner, $hosts, $registry, $renderer );

		return $script->apply( $iframe->apply( $html, $ctx ), $ctx );
	}
}
