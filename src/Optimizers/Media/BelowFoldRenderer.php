<?php
/**
 * Apply `content-visibility: auto` to below-the-fold sections.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Media;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Pure-CSS optimization: tells the browser to skip rendering work for
 * sections that aren't currently in the viewport. Improves Time to
 * Interactive on long pages without breaking SEO (the DOM still exists).
 *
 * OPT-IN PER SECTION
 * ──────────────────
 * In Feather v0.2.3 and earlier, this optimizer auto-applied to every
 * second-and-onwards `.elementor-section` / `.e-con` / `.wp-block-group`
 * / `article` with a fixed 800px intrinsic-size placeholder. That
 * caused catastrophic CLS on pages with varied section heights — every
 * size mismatch between the 800px placeholder and the real content
 * registers as a layout shift. Production sites measured CLS spikes
 * from 0.02 to 0.9+.
 *
 * v0.2.4 onwards, this optimizer only targets sections the user marks
 * with the `feather-cv` CSS class in Elementor's Advanced → CSS Classes
 * panel. The user picks which sections are below-fold AND tall-and-
 * height-stable enough to benefit. Sections the user opts in with
 * `feather-cv-NNN` (where NNN is a pixel height) get a custom
 * intrinsic-size matching their actual rendered height — measure once,
 * set forever.
 *
 * Pure CSS — no JavaScript, no DOM mutation.
 *
 * Usage examples (in Elementor editor → Advanced → CSS Classes):
 *
 *   feather-cv          → default 800px placeholder. Use when the
 *                         section is roughly 700-900px tall.
 *   feather-cv-1200     → 1200px placeholder. For taller sections.
 *   feather-cv-400      → 400px placeholder. For shorter sections.
 *
 * Browser support: Chrome / Edge / Opera. Firefox and Safari ignore the
 * property gracefully (the page renders normally with no optimization
 * and no penalty).
 */
final class BelowFoldRenderer extends AbstractOptimizer {

	public function id(): string {
		return 'f.media.below_fold_render';
	}

	public function apply(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Register an inline stylesheet via the standard WordPress API.
	 *
	 * Two rules:
	 *   1. `.feather-cv` — default 800px placeholder for users who don't
	 *       want to specify a height per section.
	 *   2. `.feather-cv-NNN` — sized variants where NNN is a pixel height.
	 *       We ship a small set of common heights
	 *       (300/400/500/600/700/900/1000/1200/1500/2000 px). Users who
	 *       need something else can use `.feather-cv` (800px default) or
	 *       add custom CSS for an unusual size.
	 */
	public function enqueue_styles(): void {
		$rules = array(
			'.feather-cv{content-visibility:auto;contain-intrinsic-size:0 800px}',
		);
		foreach ( array( 300, 400, 500, 600, 700, 900, 1000, 1200, 1500, 2000 ) as $h ) {
			$rules[] = sprintf( '.feather-cv-%1$d{content-visibility:auto;contain-intrinsic-size:0 %1$dpx}', $h );
		}

		$handle = 'feather-below-fold';
		wp_register_style( $handle, false, array(), FEATHER_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, implode( '', $rules ) );
	}
}
