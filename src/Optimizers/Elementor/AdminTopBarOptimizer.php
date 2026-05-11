<?php
/**
 * Drop the Elementor Admin Top Bar's Google Fonts fetch and bundle.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor's AdminTopBar module enqueues four assets on every wp-admin
 * pageview — one of them is an unconditional `fonts.googleapis.com` request
 * for Roboto 400/500/700. That's a third-party network hop on every screen
 * an editor opens, even pages Elementor has no business styling (Tools,
 * Posts list, Plugin installer, etc.).
 *
 * Handles dequeued (from `modules/admin-top-bar/module.php:40-69`):
 *
 *   - `elementor-admin-top-bar-fonts` — the Roboto Google Fonts <link>
 *   - `elementor-admin-top-bar`       — the top-bar stylesheet and JS
 *   - `tipsy`                          — only used by the top bar
 *
 * The top bar itself is purely cosmetic ("Go Pro" badge, version pill);
 * the editor still works fine without it.
 */
final class AdminTopBarOptimizer extends AbstractOptimizer {

	private const STYLE_HANDLES = array(
		'elementor-admin-top-bar-fonts',
		'elementor-admin-top-bar',
	);

	private const SCRIPT_HANDLES = array(
		'elementor-admin-top-bar',
		'tipsy',
	);

	public function id(): string {
		return 'f.elementor.admin_top_bar';
	}

	public function apply(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'dequeue' ), 9999 );
	}

	public function dequeue(): void {
		foreach ( self::STYLE_HANDLES as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
		foreach ( self::SCRIPT_HANDLES as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
