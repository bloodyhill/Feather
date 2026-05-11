<?php
/**
 * Force-enable Elementor's per-widget element cache.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Flips Elementor's `e_element_cache` experiment to `active` via the
 * `pre_option_*` filter.
 *
 * What `e_element_cache` does:
 *   Elementor caches each widget's rendered HTML output keyed by the
 *   widget's settings hash. On the next render, widgets whose settings
 *   haven't changed return the cached HTML directly — their PHP
 *   `render_content()` method never runs.
 *
 * Why this is the biggest lever for Elementor TTFB:
 *   Without this experiment, Elementor re-executes the full render
 *   pipeline for every widget on every request, including widgets whose
 *   output is provably static (Heading, Image, Text Editor, Button,
 *   Spacer, Divider, Icon, …). On a homepage with 40+ widgets this is
 *   commonly the dominant cost in PHP execution. Sites with Posts
 *   widgets or Loop Grids see 2-3s page renders drop into the 300-500ms
 *   range when this is on.
 *
 * Why it's default-off in Feather:
 *   The cache invalidates correctly for first-party Elementor widgets,
 *   but custom / third-party widgets that read dynamic data inside
 *   `render_content()` (querying the current user, the current date,
 *   external APIs) without declaring themselves dynamic will serve
 *   stale output. Elementor's documentation flags this as a known
 *   limitation. Production sites that use only first-party widgets are
 *   safe; sites with custom widget plugins should audit those first.
 *
 * Why we don't write to the database:
 *   Same reasoning as {@see ExperimentForcer} — `pre_option_*` filters
 *   the value at read time, leaving the stored Elementor setting
 *   untouched. Deactivating Feather restores the original state.
 */
final class ElementCacheForcer extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.element_cache';
	}

	public function apply(): void {
		add_filter( 'pre_option_elementor_experiment-e_element_cache', array( $this, 'filter_active' ) );
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
