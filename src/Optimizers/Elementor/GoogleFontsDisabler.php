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
 * Two layers cover the divergent ways Elementor injects Google Fonts:
 *
 *   1. `elementor/frontend/print_google_fonts` — Elementor's own filter
 *      short-circuits the legacy widget-typography font loop. This is what
 *      kicks in when a per-page external CSS file is rebuilt.
 *
 *   2. Kit-level handles (`elementor-google-fonts`, `e-google-fonts`)
 *      bypass that filter — they're enqueued directly from
 *      `Frontend::enqueue_styles()` when a Kit declares font families in
 *      its global typography. Filter (1) does not touch them, so we
 *      late-dequeue and deregister at `wp_print_styles:100`.
 */
final class GoogleFontsDisabler extends AbstractOptimizer {

	/**
	 * Kit-level font handles that bypass the print_google_fonts filter.
	 *
	 * @var string[]
	 */
	private const KIT_HANDLES = array(
		'elementor-google-fonts',
		'e-google-fonts',
	);

	public function id(): string {
		return 'f.elementor.google_fonts';
	}

	public function apply(): void {
		add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
		add_action( 'wp_print_styles', array( $this, 'dequeue_kit_fonts' ), 100 );
	}

	/**
	 * Drop the Kit-level Google Fonts handles before they reach the page.
	 */
	public function dequeue_kit_fonts(): void {
		foreach ( self::KIT_HANDLES as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}
}
