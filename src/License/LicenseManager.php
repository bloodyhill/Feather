<?php
/**
 * License manager — dormant skeleton at launch.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\License;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether Pro-tagged features are unlocked.
 *
 * Phase 1 (launch): is_pro_active() always returns true while the
 * FEATHER_ALL_FREE constant is defined. The implementation is otherwise
 * fully scaffolded so the future feather-pro add-on can drop in a real
 * implementation without changing callers.
 *
 * Grandfather contract:
 *   Every install records the date Feather was first activated. When
 *   paywalling is later introduced, an install whose `feather_install_date`
 *   predates the cutover keeps every currently-enabled feature for life.
 *   Implementation lives here so the test surface is one class.
 */
final class LicenseManager {

	public const INSTALL_DATE_OPTION = 'feather_install_date';

	/**
	 * Whether Pro-tagged features are unlocked for this install.
	 */
	public function is_pro_active(): bool {
		if ( defined( 'FEATHER_ALL_FREE' ) && FEATHER_ALL_FREE ) {
			return true;
		}

		// Future: validate cached license payload + apply grandfather rule.
		// For now, the constant being absent means the future add-on owns the decision.
		return false;
	}

	/**
	 * The recorded install date (ISO 8601 GMT). Stamps it on first call
	 * if not already set.
	 */
	public function install_date(): string {
		$date = (string) get_option( self::INSTALL_DATE_OPTION, '' );
		if ( '' === $date ) {
			$date = gmdate( 'c' );
			update_option( self::INSTALL_DATE_OPTION, $date, true );
		}

		return $date;
	}
}
