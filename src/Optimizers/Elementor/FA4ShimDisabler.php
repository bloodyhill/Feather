<?php
/**
 * Disable Elementor's Font Awesome 4 backwards-compatibility shim.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the Font Awesome 4 shim Elementor still ships for legacy widgets.
 * Saves ~70KB on every page load. Modern Elementor sites do not need it.
 */
final class FA4ShimDisabler extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.fa4_shim';
	}

	public function apply(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_fa_assets' ), 9999 );
	}

	/**
	 * Deregister and dequeue Elementor's FA4 stylesheets.
	 */
	public function dequeue_fa_assets(): void {
		$handles = array(
			'elementor-icons-fa-solid',
			'elementor-icons-fa-brands',
			'elementor-icons-fa-regular',
			'elementor-icons-shared-0',
		);

		foreach ( $handles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}
}
