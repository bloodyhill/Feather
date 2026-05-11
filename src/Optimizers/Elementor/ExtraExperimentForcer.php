<?php
/**
 * Opt-in companion to ExperimentForcer that flips three additional
 * Elementor experiments that look like pure wins but require attention
 * to ship safely.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Forces three additional Elementor performance experiments to "active":
 *
 *   - `e_optimized_assets_loading` Elementor stops enqueuing the union of
 *                                  every widget's CSS on every page and
 *                                  ships only per-widget styles instead.
 *                                  REQUIRES the user to run
 *                                  Elementor → Tools → Regenerate Files &
 *                                  Data first — until those per-widget
 *                                  CSS files exist, widgets render briefly
 *                                  unstyled and CLS spikes.
 *
 *   - `e_lazyload`                 Elementor adds `loading="lazy"` to its
 *                                  Image widget output. Saves bandwidth on
 *                                  below-fold images, but UNSAFE on pages
 *                                  whose above-fold images lack explicit
 *                                  width/height attributes — those images
 *                                  load after first paint and shift the
 *                                  layout. Pair with the Auto-fix image
 *                                  dimensions feature (`f.media.image_dimensions`)
 *                                  for full coverage.
 *
 *   - `e_css_smooth_scroll`        Anchor-link smooth scroll runs as CSS
 *                                  `scroll-behavior: smooth` instead of via
 *                                  jQuery `$.animate()`. Drops a chunk of
 *                                  legacy JS from the critical path with
 *                                  no rendering impact.
 *
 * Default-off because Feather v0.2.1 shipped these as forced-on and caused
 * CLS / LCP regressions on real sites that hadn't regenerated CSS files or
 * whose Elementor images lacked dimensions. Users opt in after they've
 * (a) regenerated Elementor files and (b) confirmed image dimensions are
 * being added by Feather's existing `ImageDimensionsAdder`.
 *
 * Like {@see ExperimentForcer}, this uses `pre_option_*` filters so no
 * database write happens; deactivating the feature restores the stored
 * Elementor setting immediately.
 */
final class ExtraExperimentForcer extends AbstractOptimizer {

	/**
	 * Experiment keys to force on. Full option name is
	 * `elementor_experiment-{key}`.
	 *
	 * @var string[]
	 */
	private const EXPERIMENTS = array(
		'e_optimized_assets_loading',
		'e_lazyload',
		'e_css_smooth_scroll',
	);

	public function id(): string {
		return 'f.elementor.force_extra_experiments';
	}

	public function apply(): void {
		foreach ( self::EXPERIMENTS as $experiment ) {
			add_filter(
				'pre_option_elementor_experiment-' . $experiment,
				array( $this, 'filter_active' )
			);
		}
	}

	/**
	 * Force the experiment option to Elementor's `STATE_ACTIVE` value.
	 *
	 * Literal `'active'` matches `\Elementor\Core\Experiments\Manager::STATE_ACTIVE`.
	 *
	 * @return string
	 */
	public function filter_active() {
		return 'active';
	}
}
