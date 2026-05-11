<?php
/**
 * GET /system/info — read-only environment snapshot for Settings.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
use Feather\Compat\PluginDetector;
use Feather\License\LicenseManager;
use Feather\Settings\SchemaMigrator;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Surfaces plugin metadata, environment info, and detected coexisting
 * plugins so the Settings page can show the user what's going on without
 * needing them to dig through a debug log.
 */
final class SystemInfoEndpoint implements RouteRegistrar {

	/**
	 * License manager (used for install date).
	 *
	 * @var LicenseManager
	 */
	private $license;

	/**
	 * Plugin detector.
	 *
	 * @var PluginDetector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param LicenseManager  $license  License.
	 * @param PluginDetector  $detector Detector.
	 */
	public function __construct( LicenseManager $license, PluginDetector $detector ) {
		$this->license  = $license;
		$this->detector = $detector;
	}

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/system/info',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'info' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);
	}

	public function permission_check(): bool {
		return Capability::user_can();
	}

	/**
	 * GET /system/info.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function info( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		global $wp_version;

		return new WP_REST_Response(
			array(
				'plugin'   => array(
					'version'        => FEATHER_VERSION,
					'install_date'   => $this->license->install_date(),
					'schema_version' => SchemaMigrator::SCHEMA_VERSION,
				),
				'env'      => array(
					'wp_version'  => is_string( $wp_version ) ? $wp_version : '',
					'php_version' => PHP_VERSION,
					'multisite'   => is_multisite(),
					'locale'      => determine_locale(),
				),
				'detected' => $this->detector->detect_all(),
			),
			200
		);
	}
}
