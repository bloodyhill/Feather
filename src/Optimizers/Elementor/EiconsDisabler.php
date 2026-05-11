<?php
/**
 * Disable Elementor's eicons stylesheet globally.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Drops the Elementor eicons font/stylesheet entirely. Only safe to enable
 * when no widget on the site references an eicon — the scanner gates this
 * feature and the UI badge reflects the verdict.
 */
final class EiconsDisabler extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.eicons_global';
	}

	public function apply(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_eicons' ), 9999 );
	}

	/**
	 * Dequeue and deregister the eicons stylesheet.
	 */
	public function dequeue_eicons(): void {
		wp_dequeue_style( 'elementor-icons' );
		wp_deregister_style( 'elementor-icons' );
	}
}
