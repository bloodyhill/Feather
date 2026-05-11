<?php
/**
 * Force-enable Elementor's own performance experiments.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Flips Elementor's built-in performance experiments to "active" via the
 * `pre_option_*` filter, without writing to the database — the user can
 * still override the setting in Elementor → Settings → Features, and
 * deactivating Feather restores the original state immediately.
 *
 * Why force these instead of writing more Feather code:
 *
 *   - `e_font_icon_svg`     With this active, `Frontend::register_styles()`
 *                           skips enqueuing both `elementor-icons` (eicons)
 *                           and the Font Awesome library handles; icons
 *                           render as inline SVG. Makes both EiconsDisabler
 *                           and FA4ShimDisabler redundant on those pages.
 *                           Elementor ships this as default-active for new
 *                           sites since 3.17.0.
 *
 *   - `e_optimized_markup`  Removes unused wrapper `<div>` / `<span>` tags
 *                           from widget output across the codebase (every
 *                           widget file checks this in `has_widget_inner_wrapper`).
 *                           Smaller DOM, faster paint. Default-active for
 *                           new sites since 3.30.0; legacy installs stay off.
 *
 * Container and breakpoint experiments are intentionally NOT forced — those
 * change rendering behavior, not bloat output, and could break themes built
 * against the older Elementor layout model.
 *
 * Three more experiments (`e_optimized_assets_loading`, `e_lazyload`,
 * `e_css_smooth_scroll`) that look like pure wins in isolation can produce
 * CLS / LCP regressions on production sites:
 *   - `e_lazyload` lazy-loads every Image widget, including above-fold ones;
 *     images without explicit width/height trigger layout shift when they
 *     arrive after first paint.
 *   - `e_optimized_assets_loading` only ships per-widget CSS, but the
 *     per-widget files don't exist until the user runs Elementor → Tools →
 *     Regenerate Files & Data; without that, widgets briefly render unstyled.
 * Those three are now exposed as the separate, opt-in {@see ExtraExperimentForcer}.
 */
final class ExperimentForcer extends AbstractOptimizer {

	/**
	 * Experiment keys to force on. The full option name is
	 * `elementor_experiment-{key}` — matches what
	 * `\Elementor\Core\Experiments\Manager` reads via `get_option()`.
	 *
	 * @var string[]
	 */
	private const EXPERIMENTS = array(
		'e_font_icon_svg',
		'e_optimized_markup',
	);

	public function id(): string {
		return 'f.elementor.force_experiments';
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
	 * The literal `'active'` matches
	 * `\Elementor\Core\Experiments\Manager::STATE_ACTIVE` and is kept as
	 * a string so the filter still works when Elementor's class isn't
	 * loaded yet at the moment the filter fires.
	 *
	 * @return string
	 */
	public function filter_active() {
		return 'active';
	}
}
