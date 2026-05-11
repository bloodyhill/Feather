<?php
/**
 * Disable Elementor telemetry, beta-tester, and tracker events.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Forces Elementor's tracking-related options to "no" via the
 * `pre_option_*` filters and removes any registered tracker events.
 */
final class TelemetryDisabler extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.telemetry';
	}

	public function apply(): void {
		add_filter( 'pre_option_elementor_allow_tracking', array( $this, 'filter_no' ) );
		add_filter( 'pre_option_elementor_tracker_notice', array( $this, 'filter_one' ) );
		add_filter( 'pre_option_elementor_beta', array( $this, 'filter_no' ) );

		// Drop any tracker-event listeners registered by Elementor or add-ons.
		remove_all_actions( 'elementor/tracker/send_event' );
	}

	/**
	 * Force-return the string "no" for tracking-allow flags.
	 *
	 * @return string
	 */
	public function filter_no() {
		return 'no';
	}

	/**
	 * Force-return the string "1" — Elementor stores the dismissed-notice flag
	 * as a stringified integer.
	 *
	 * @return string
	 */
	public function filter_one() {
		return '1';
	}
}
