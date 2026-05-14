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

	public const INSTALL_DATE_OPTION = 'feather_install_date';

	/**
	 * Plugin detector.
	 *
	 * @var PluginDetector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param PluginDetector $detector Detector.
	 */
	public function __construct( PluginDetector $detector ) {
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
					'install_date'   => $this->install_date(),
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

	/**
	 * The recorded install date (ISO 8601 GMT). Stamps it on first call
	 * if not already set.
	 */
	private function install_date(): string {
		$date = (string) get_option( self::INSTALL_DATE_OPTION, '' );
		if ( '' === $date ) {
			$date = gmdate( 'c' );
			update_option( self::INSTALL_DATE_OPTION, $date, true );
		}

		return $date;
	}
}
