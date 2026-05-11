<?php
/**
 * Throttle WP Heartbeat — slower in admin, off on the frontend.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Wp;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Stretches the admin heartbeat from 15s to 60s and removes the heartbeat
 * script entirely on the public frontend, where its only consumer would be
 * a logged-in admin browsing their own site.
 */
final class HeartbeatThrottler extends AbstractOptimizer {

	public const ADMIN_INTERVAL_SECONDS = 60;

	public function id(): string {
		return 'f.wp.heartbeat';
	}

	public function apply(): void {
		add_filter( 'heartbeat_settings', array( $this, 'lengthen_interval' ) );
		add_action( 'init', array( $this, 'deregister_on_frontend' ), 1 );
	}

	/**
	 * Stretch the admin heartbeat interval.
	 *
	 * @param mixed $settings Heartbeat settings.
	 * @return array
	 */
	public function lengthen_interval( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array( 'interval' => self::ADMIN_INTERVAL_SECONDS );
		}
		$settings['interval'] = self::ADMIN_INTERVAL_SECONDS;
		return $settings;
	}

	/**
	 * Drop heartbeat from the frontend entirely.
	 */
	public function deregister_on_frontend(): void {
		if ( is_admin() ) {
			return;
		}
		wp_deregister_script( 'heartbeat' );
	}
}
