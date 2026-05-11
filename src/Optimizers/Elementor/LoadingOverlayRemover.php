<?php
/**
 * Hide Elementor's full-page loading overlay.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor injects a full-viewport white overlay with a spinner at
 * `<body>` open, then fades it out once `elementor-frontend` finishes
 * booting. The intent is to mask a flash of unstyled content, but on
 * sites where Feather has already trimmed render-blocking CSS the page
 * paints fully before JS runs — and the overlay now sits *on top of*
 * already-painted content, actively delaying first meaningful paint.
 *
 * We hide it with a single CSS rule emitted at `wp_head:0` so the
 * overlay never becomes visible even before its own stylesheet loads.
 * Pure CSS — no JS, no DOM mutation, no race with Elementor's own boot.
 *
 * Selectors cover both the legacy id (`#elementor-loading`) and the
 * modern class form (`.elementor-loading-overlay`).
 */
final class LoadingOverlayRemover extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.loading_overlay';
	}

	public function apply(): void {
		add_action( 'wp_head', array( $this, 'print_style' ), 0 );
	}

	/**
	 * Emit the hide rule. Priority 0 means it lands before any other
	 * head content can paint the overlay.
	 */
	public function print_style(): void {
		if ( is_admin() ) {
			return;
		}
		echo "<style id=\"feather-no-loading-overlay\">#elementor-loading,.elementor-loading-overlay{display:none!important}</style>\n";
	}
}
