<?php
/**
 * The built detection/render pipeline as one value (PLAN.md §2.2). Plugin
 * builds it lazily — see Plugin::pipeline() for why lazy — and everything
 * downstream receives non-nullable, fully-constructed collaborators instead
 * of ten independently-nullable properties that were only ever set together.
 *
 * WordPress-free: plain constructed objects in, public readonly-by-convention
 * properties out. The WordPress bridges are baked into the collaborators as
 * injected callables before they get here.
 *
 * @package CaluconEmbedGate
 */

namespace CaluconEmbedGate\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CaluconEmbedGate\Detection\BackgroundVideoRule;
use CaluconEmbedGate\Detection\BuilderEmbedRule;
use CaluconEmbedGate\Detection\ElementorVideoRule;
use CaluconEmbedGate\Detection\EmbedObjectRule;
use CaluconEmbedGate\Detection\EmbedStripper;
use CaluconEmbedGate\Detection\HostMatcher;
use CaluconEmbedGate\Detection\HtmlScanner;
use CaluconEmbedGate\Detection\IframeRule;
use CaluconEmbedGate\Detection\ImageRule;
use CaluconEmbedGate\Detection\ScriptRule;
use CaluconEmbedGate\Detection\StylesheetRule;
use CaluconEmbedGate\Providers\Registry;
use CaluconEmbedGate\Rendering\PlaceholderRenderer;

/**
 * Everything Plugin::build once wired lazily, bundled so "built" is a single
 * fact: either the Pipeline exists and every part of it does, or it does not.
 */
final class Pipeline {

	/** @var BackgroundVideoRule */
	public BackgroundVideoRule $background_video_rule;

	/** @var ElementorVideoRule */
	public ElementorVideoRule $elementor_video_rule;

	/** @var BuilderEmbedRule */
	public BuilderEmbedRule $builder_embed_rule;

	/** @var IframeRule */
	public IframeRule $iframe_rule;

	/** @var EmbedObjectRule */
	public EmbedObjectRule $embed_object_rule;

	/** @var ScriptRule */
	public ScriptRule $script_rule;

	/** @var ImageRule */
	public ImageRule $image_rule;

	/** @var StylesheetRule */
	public StylesheetRule $stylesheet_rule;

	/** @var Registry */
	public Registry $registry;

	/** @var HostMatcher */
	public HostMatcher $host_matcher;

	/** @var EmbedStripper */
	public EmbedStripper $stripper;

	/** @var HtmlScanner */
	public HtmlScanner $scanner;

	/** @var ResourceHints */
	public ResourceHints $hint_scrubber;

	/** @var PlaceholderRenderer */
	public PlaceholderRenderer $renderer;

	/**
	 * @param BackgroundVideoRule $background_video_rule Third-party background videos, removed.
	 * @param ElementorVideoRule  $elementor_video_rule  Elementor players that exist only as JSON.
	 * @param BuilderEmbedRule    $builder_embed_rule    Other players built in the browser (Enfold, facades).
	 * @param IframeRule          $iframe_rule       Cross-origin iframe gate.
	 * @param EmbedObjectRule     $embed_object_rule Legacy embed/object gate.
	 * @param ScriptRule          $script_rule       SDK-script gate.
	 * @param ImageRule           $image_rule        Opt-in third-party image gate.
	 * @param StylesheetRule      $stylesheet_rule   Provider stylesheets as silent companions.
	 * @param Registry            $registry          Provider descriptors.
	 * @param HostMatcher         $host_matcher      "Is this ours?".
	 * @param EmbedStripper       $stripper          Excerpt/feed removal.
	 * @param HtmlScanner         $scanner           Attribute-tolerant tag reader.
	 * @param ResourceHints       $hint_scrubber     Hint scrubbing.
	 * @param PlaceholderRenderer $renderer          The §5.1 markup contract.
	 */
	public function __construct(
		BackgroundVideoRule $background_video_rule,
		ElementorVideoRule $elementor_video_rule,
		BuilderEmbedRule $builder_embed_rule,
		IframeRule $iframe_rule,
		EmbedObjectRule $embed_object_rule,
		ScriptRule $script_rule,
		ImageRule $image_rule,
		StylesheetRule $stylesheet_rule,
		Registry $registry,
		HostMatcher $host_matcher,
		EmbedStripper $stripper,
		HtmlScanner $scanner,
		ResourceHints $hint_scrubber,
		PlaceholderRenderer $renderer
	) {
		$this->background_video_rule = $background_video_rule;
		$this->elementor_video_rule  = $elementor_video_rule;
		$this->builder_embed_rule    = $builder_embed_rule;
		$this->iframe_rule           = $iframe_rule;
		$this->embed_object_rule     = $embed_object_rule;
		$this->script_rule           = $script_rule;
		$this->image_rule            = $image_rule;
		$this->stylesheet_rule       = $stylesheet_rule;
		$this->registry              = $registry;
		$this->host_matcher          = $host_matcher;
		$this->stripper              = $stripper;
		$this->scanner               = $scanner;
		$this->hint_scrubber         = $hint_scrubber;
		$this->renderer              = $renderer;
	}
}
