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
 * Per-context heartbeat tuning.
 *
 * Three knobs live under settings.advanced.heartbeat:
 *   frontend_disabled: whether to drop heartbeat from the public frontend (bool)
 *   editor_interval:   block-editor / Gutenberg interval in seconds (15-600)
 *   admin_interval:    other wp-admin pages interval in seconds (15-600)
 *
 * Editor and admin are tuned separately because the block editor uses
 * heartbeat for autosaves and post locking, where killing it outright would
 * break collaboration. Other admin pages — Tools, Plugins, the Feather
 * dashboard itself — see no benefit from a fast heartbeat.
 */
final class HeartbeatThrottler extends AbstractOptimizer {

	public function id(): string {
		return 'f.wp.heartbeat';
	}

	public function apply(): void {
		add_filter( 'heartbeat_settings', array( $this, 'lengthen_interval' ) );
		add_action( 'init', array( $this, 'deregister_on_frontend' ), 1 );
	}

	/**
	 * Stretch the heartbeat interval according to the active context.
	 *
	 * @param mixed $settings Heartbeat settings.
	 * @return array
	 */
	public function lengthen_interval( $settings ) {
		$advanced = $this->settings->heartbeat_advanced();
		$interval = $this->is_block_editor_request()
			? $advanced['editor_interval']
			: $advanced['admin_interval'];

		if ( ! is_array( $settings ) ) {
			return array( 'interval' => $interval );
		}
		$settings['interval'] = $interval;
		return $settings;
	}

	/**
	 * Drop heartbeat from the frontend entirely, unless the user opts to keep
	 * it on.
	 */
	public function deregister_on_frontend(): void {
		if ( is_admin() ) {
			return;
		}
		$advanced = $this->settings->heartbeat_advanced();
		if ( ! $advanced['frontend_disabled'] ) {
			return;
		}
		wp_deregister_script( 'heartbeat' );
	}

	/**
	 * Whether the current admin request is a block-editor page.
	 *
	 * We can't use wp_is_block_editor() here because it depends on the
	 * current_screen being set, and heartbeat_settings fires earlier than
	 * that. The pagenow / GET fallback is the same heuristic core uses.
	 */
	private function is_block_editor_request(): bool {
		global $pagenow;
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php', 'site-editor.php' ), true ) ) {
			return true;
		}
		return false;
	}
}
