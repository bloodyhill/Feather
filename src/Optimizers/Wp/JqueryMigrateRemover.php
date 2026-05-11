<?php
/**
 * Remove jquery-migrate from the frontend.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Wp;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the jQuery Migrate dependency from the public-facing jQuery
 * registration. Modern themes and plugins have caught up — Migrate is
 * legacy weight on most sites. Admin keeps Migrate so the editor and
 * older plugins remain compatible.
 */
final class JqueryMigrateRemover extends AbstractOptimizer {

	public function id(): string {
		return 'f.wp.jquery_migrate';
	}

	public function apply(): void {
		add_action( 'wp_default_scripts', array( $this, 'strip_migrate_dep' ) );
	}

	/**
	 * Drop `jquery-migrate` from jQuery's frontend dependency list.
	 *
	 * @param mixed $scripts WP_Scripts instance.
	 */
	public function strip_migrate_dep( $scripts ): void {
		if ( is_admin() ) {
			return;
		}
		if ( ! is_object( $scripts ) || ! isset( $scripts->registered ) || ! is_array( $scripts->registered ) ) {
			return;
		}
		if ( ! isset( $scripts->registered['jquery'] ) ) {
			return;
		}
		$jquery = $scripts->registered['jquery'];
		if ( ! is_object( $jquery ) || ! isset( $jquery->deps ) || ! is_array( $jquery->deps ) ) {
			return;
		}
		$jquery->deps = array_values( array_diff( $jquery->deps, array( 'jquery-migrate' ) ) );
	}
}
