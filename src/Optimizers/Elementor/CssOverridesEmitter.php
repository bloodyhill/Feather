<?php
/**
 * Emit Feather's surgical CSS overrides for Elementor widgets.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;
use Feather\Scanner\ElementorPageDetector;
use Feather\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Inlines ~2 KB of CSS that addresses the highest-frequency Cumulative
 * Layout Shift and animation-cost issues in Elementor widgets:
 *
 *   - Image / Image Box     reserve aspect-ratio so unloaded images
 *                           don't push surrounding text on arrival.
 *   - Image Carousel        minimum Swiper-wrapper height before init.
 *   - Counter               fixed-width digit reserve so the number
 *                           animation doesn't re-flow its parent.
 *   - Tabs                  hide inactive panels with visibility + 0
 *                           height (cheaper than `display:none` toggles).
 *   - Icon List             flex/gap layout instead of margin spacing.
 *   - Video                 reserve 16:9 aspect-ratio so the iframe
 *                           doesn't push content when it loads.
 *   - Motion effects        `will-change: transform` on entry; cleared
 *                           after the animation lands so the layer is
 *                           released back to the compositor.
 *   - prefers-reduced-motion fully disables Elementor entrance and
 *                           motion-effect animations, including paused
 *                           swiper transitions, for users who set it.
 *   - print                 strips interactive widgets, drops shadows,
 *                           collapses columns to a single column.
 *
 * Theme-coupled overrides (system font stacks, fluid `clamp()` typography,
 * modern grid replacement) are intentionally *not* shipped here — those
 * change the visual design and belong in a child theme, not a perf plugin.
 *
 * The stylesheet is emitted only on requests the page detector classifies
 * as Elementor-rendered, so non-Elementor pages don't pay the parser cost.
 */
final class CssOverridesEmitter extends AbstractOptimizer {

	/**
	 * Page detector.
	 *
	 * @var ElementorPageDetector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings repository.
	 */
	public function __construct( SettingsRepository $settings ) {
		parent::__construct( $settings );
		$this->detector = new ElementorPageDetector();
	}

	public function id(): string {
		return 'f.elementor.css_overrides_cls';
	}

	public function apply(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Register and enqueue the override stylesheet inline via the standard
	 * WordPress API. The empty-src handle exists only to anchor the inline
	 * CSS; WordPress prints it alongside the rest of the page's stylesheets
	 * during wp_head, before any below-fold image starts loading.
	 */
	public function enqueue_style(): void {
		if ( ! $this->detector->current_request_uses_elementor() ) {
			return;
		}

		// Minified for the wire; one line per rule group for grep-ability.
		$css = ''
			. '.elementor-widget-image img{display:block;max-width:100%;height:auto}'
			. '.elementor-widget-image img[width][height]{aspect-ratio:attr(width) / attr(height)}'
			. '.elementor-image-box-wrapper{align-items:flex-start}'
			. '.elementor-image-box-img{flex-shrink:0}'
			. '.elementor-section.elementor-section-has-background{min-block-size:1px}'
			. '.elementor-widget-image-carousel .swiper-wrapper{min-height:200px}'
			. '.elementor-widget-image-carousel .swiper-slide img{width:100%;height:auto;object-fit:cover}'
			. '.elementor-counter-number-wrapper{display:inline-block;min-width:3ch;text-align:right}'
			. '.elementor-tab-content:not(.elementor-active){height:0;overflow:hidden;visibility:hidden;pointer-events:none}'
			. '.elementor-tab-content.elementor-active{height:auto;visibility:visible;pointer-events:auto}'
			. '.elementor-icon-list-items{padding:0;margin:0;list-style:none}'
			. '.elementor-icon-list-item{display:flex;align-items:center;gap:.5em}'
			. '.elementor-widget-video .elementor-wrapper{aspect-ratio:16/9;width:100%}'
			. '.elementor-widget-video .elementor-wrapper iframe,.elementor-widget-video .elementor-wrapper video{width:100%;height:100%}'
			. '.elementor-motion-effects-element{will-change:transform}'
			. '.elementor-motion-effects-element.elementor-motion-effects-element-type-background{will-change:transform;contain:layout paint}'
			. '.elementor-invisible.animated{will-change:auto}'
			. '@media(prefers-reduced-motion:reduce){.elementor-invisible{opacity:1!important;transform:none!important;animation:none!important;transition:none!important}.animated{animation:none!important}.elementor-motion-effects-element{will-change:auto!important;transform:none!important}.swiper-wrapper{transition-duration:0ms!important}}'
			. '@media print{.elementor-widget-nav-menu,.elementor-widget-image-carousel,.elementor-widget-video,.elementor-widget-google_maps,.elementor-popup-modal,.elementor-motion-effects-container{display:none!important}.elementor-widget-text-editor,.elementor-heading-title{color:#000!important;background:transparent!important}.elementor-column{width:100%!important;float:none!important}.elementor-section,.elementor-column,.elementor-widget-wrap{box-shadow:none!important}}';

		$handle = 'feather-elementor-overrides';
		wp_register_style( $handle, false, array(), FEATHER_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}
}
