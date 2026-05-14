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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Register and enqueue the hide rule inline via the standard WordPress
	 * API. The `!important` guarantees the rule wins regardless of the
	 * order WordPress prints stylesheets in wp_head.
	 */
	public function enqueue_style(): void {
		$handle = 'feather-no-loading-overlay';
		wp_register_style( $handle, false, array(), FEATHER_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			'#elementor-loading,.elementor-loading-overlay{display:none!important}'
		);
	}
}
