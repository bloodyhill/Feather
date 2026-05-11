<?php
/**
 * Block outbound HTTP to elementor.com unless the request is user-initiated.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Aggressive network firewall: any outbound `wp_remote_*` request whose URL
 * host matches `(^|\.)elementor\.com$` is refused unless we can confirm the
 * request originated from a user-initiated action (AJAX inside the editor,
 * REST request, editor page load).
 *
 * Catches every background telemetry / marketing / discovery vector at the
 * network layer — including future ones that ship in later Elementor versions
 * — without having to enumerate each one.
 *
 * Coexists with `ApiFetcherDisabler`: this hooks `pre_http_request` at priority
 * 1 (runs first); `ApiFetcherDisabler` runs at default priority 10. When this
 * firewall returns a WP_Error for a blocked request, the later filter sees
 * `$preempt !== false` and short-circuits — no double-handling.
 */
final class ElementorHostFirewall extends AbstractOptimizer {

	/**
	 * Hostname regex matching `elementor.com` and any subdomain (case-insensitive).
	 *
	 * @var string
	 */
	private const HOST_REGEX = '/(^|\.)elementor\.com$/i';

	public function id(): string {
		return 'f.elementor.network_firewall';
	}

	public function apply(): void {
		// Priority 1 — run before ApiFetcherDisabler (priority 10) and any
		// other plugin's pre_http_request filter.
		add_filter( 'pre_http_request', array( $this, 'maybe_block' ), 1, 3 );
	}

	/**
	 * Decide whether to block this outbound request.
	 *
	 * @param false|array|\WP_Error $preempt Existing short-circuit value.
	 * @param array<string, mixed>  $args    Request args (unused).
	 * @param string                $url     Target URL.
	 * @return false|array|\WP_Error
	 */
	public function maybe_block( $preempt, $args, $url ) {
		unset( $args );

		// Someone else already short-circuited — defer.
		if ( false !== $preempt ) {
			return $preempt;
		}
		if ( ! is_string( $url ) || '' === $url ) {
			return $preempt;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return $preempt;
		}
		if ( ! preg_match( self::HOST_REGEX, $host ) ) {
			return $preempt;
		}

		if ( $this->is_user_initiated() ) {
			return $preempt;
		}

		return new \WP_Error(
			'feather_firewall_blocked',
			sprintf(
				/* translators: %s is the blocked hostname. */
				esc_html__( 'Outbound to %s blocked by Feather network firewall.', 'feather-performance' ),
				$host
			)
		);
	}

	/**
	 * Whether the current request is something the user explicitly triggered
	 * (editor click, REST call from the editor, editor page load) vs. a
	 * background WordPress lifecycle phase (cron, admin_init, heartbeat,
	 * transient refresh).
	 */
	private function is_user_initiated(): bool {
		if ( wp_doing_cron() ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			// Heartbeat is scheduled background polling, not a user click.
			$action = isset( $_REQUEST['action'] )
				? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				: '';
			return 'heartbeat' !== $action;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Editor page load (admin.php?action=elementor&post=...).
		if (
			is_admin()
			&& isset( $_GET['action'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& 'elementor' === sanitize_key( wp_unslash( $_GET['action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			return true;
		}

		return false;
	}
}
