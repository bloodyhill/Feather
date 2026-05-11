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
 * Targets the second-and-onwards Elementor section / Flexbox container /
 * WP block container — we deliberately skip the first one because it's
 * almost always above the fold and `content-visibility: auto` would defer
 * its paint by a frame.
 *
 * Both layout models are matched:
 *   - `.elementor-section`  — legacy Section/Column structure
 *   - `.e-con`              — Flexbox Container (Elementor 3.6+, default
 *                             on new sites since 3.16)
 *
 * The `contain-intrinsic-size` reservation prevents scroll-jank when
 * sections enter the viewport.
 */
final class BelowFoldRenderer extends AbstractOptimizer {

	public function id(): string {
		return 'f.media.below_fold_render';
	}

	public function apply(): void {
		add_action( 'wp_head', array( $this, 'print_styles' ), 100 );
	}

	/**
	 * Emit the CSS rules to wp_head.
	 */
	public function print_styles(): void {
		if ( is_admin() ) {
			return;
		}
		echo "<style id=\"feather-below-fold\">.elementor-section:nth-of-type(n+2),.e-con:nth-of-type(n+2),.wp-block-group:nth-of-type(n+2),article:nth-of-type(n+2){content-visibility:auto;contain-intrinsic-size:0 800px}</style>\n";
	}
}
