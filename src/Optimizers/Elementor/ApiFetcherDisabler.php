<?php
/**
 * Short-circuit Elementor's periodic phone-home to my.elementor.com.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * `\Elementor\Api::get_info_data()` (`/includes/api.php:58-86`) is the single
 * source for every "promotional / informational" remote fetch Elementor
 * makes — Black Friday banners, Birthday banners, admin notices, pro-widget
 * upsell registrations, "what's new" feed, and the canary deployment check.
 * They all key off one transient: `elementor_remote_info_api_data_<VERSION>`.
 *
 * We short-circuit the transient with a pre-populated empty payload so:
 *
 *   - `get_transient()` returns a non-falsey value, so the actual remote
 *     `wp_remote_get()` to `my.elementor.com/api/v2/info/` is skipped.
 *   - Every reader sees no upgrade notices, no banner data, no pro widgets
 *     to register — they all bail cleanly because the relevant array keys
 *     just aren't there.
 *
 * Defense-in-depth: also block any outbound request to `my.elementor.com`
 * via `pre_http_request`, which catches sibling fetchers we haven't mapped
 * (the Connect library sync paths, the deactivate/uninstall pings).
 */
final class ApiFetcherDisabler extends AbstractOptimizer {

	private const TRANSIENT_PREFIX = 'elementor_remote_info_api_data_';

	private const REMOTE_HOST = 'my.elementor.com';

	public function id(): string {
		return 'f.elementor.api_fetcher';
	}

	public function apply(): void {
		add_filter(
			'pre_transient_' . self::TRANSIENT_PREFIX . $this->elementor_version(),
			array( $this, 'fake_empty_payload' )
		);
		add_filter( 'pre_http_request', array( $this, 'block_elementor_remote' ), 10, 3 );
	}

	/**
	 * Return an empty-but-shaped payload so callers see "data present, just
	 * nothing interesting in it" and don't trigger a refresh.
	 *
	 * @return array<string, mixed>
	 */
	public function fake_empty_payload() {
		return array(
			'feed'              => array(),
			'admin_notice'      => array(),
			'upgrade_notice'    => array(),
			'canary_deployment' => array(),
			'pro_widgets'       => array(),
		);
	}

	/**
	 * Refuse outbound HTTP to my.elementor.com.
	 *
	 * Returning a `WP_Error` short-circuits the request — `wp_remote_get()`
	 * gets the error and Elementor's callers treat that as "no data," same
	 * as the transient short-circuit above.
	 *
	 * @param false|array|\WP_Error $preempt Existing short-circuit value.
	 * @param array                 $args    Request args.
	 * @param string                $url     Target URL.
	 * @return false|array|\WP_Error
	 */
	public function block_elementor_remote( $preempt, $args, $url ) {
		unset( $args );
		if ( false === $preempt && is_string( $url ) && false !== strpos( $url, self::REMOTE_HOST ) ) {
			return new \WP_Error( 'feather_blocked', 'Elementor remote calls disabled by Feather.' );
		}
		return $preempt;
	}

	/**
	 * Resolve the running Elementor version so we build the right transient
	 * key. Falls back to an empty string when Elementor isn't loaded yet —
	 * the filter still registers but never matches until Elementor defines
	 * the constant on next request.
	 */
	private function elementor_version(): string {
		return defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '';
	}
}
