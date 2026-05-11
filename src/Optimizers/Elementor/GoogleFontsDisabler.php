<?php
/**
 * Disable Elementor's Google Fonts output.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Stops Elementor from emitting Google Fonts links. Use only when the theme
 * already provides web fonts or you self-host them via a font-host plugin.
 *
 * Implemented via the `elementor/frontend/print_google_fonts` filter so
 * the stripping happens cleanly inside Elementor's own enqueue pipeline.
 */
final class GoogleFontsDisabler extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.google_fonts';
	}

	public function apply(): void {
		add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
	}
}
