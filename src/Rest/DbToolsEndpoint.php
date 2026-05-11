<?php
/**
 * REST endpoints for the Database screen.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
use Feather\Db\DbToolsService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Surface for the React Database screen.
 *
 *   GET  /db/health             — composite health snapshot for the dashboard
 *   GET  /db/autoload           — top autoloaded options + total bytes
 *   POST /db/cleanup/{tool}     — run a cleanup tool by name
 */
final class DbToolsEndpoint implements RouteRegistrar {

	/**
	 * Allowed cleanup tool names.
	 *
	 * @var string[]
	 */
	private const ALLOWED_TOOLS = array(
		DbToolsService::TOOL_TRANSIENTS,
		DbToolsService::TOOL_ELEMENTOR_REVISIONS,
		DbToolsService::TOOL_OEMBED_CACHE,
	);

	/**
	 * Service.
	 *
	 * @var DbToolsService
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param DbToolsService $service Service.
	 */
	public function __construct( DbToolsService $service ) {
		$this->service = $service;
	}

	public function register_routes( string $namespace ): void {
		$permission = array( $this, 'permission_check' );

		register_rest_route(
			$namespace,
			'/db/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$namespace,
			'/db/autoload',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'autoload' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$namespace,
			'/db/cleanup/(?P<tool>[a-z0-9_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cleanup' ),
				'permission_callback' => $permission,
				'args'                => array(
					'tool' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_tool' ),
					),
				),
			)
		);
	}

	public function permission_check(): bool {
		return Capability::user_can();
	}

	/**
	 * Sanitize tool name from URL.
	 *
	 * @param mixed $value Incoming value.
	 */
	public function sanitize_tool( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return preg_replace( '/[^a-z0-9_]/', '', strtolower( $value ) ) ?? '';
	}

	/**
	 * GET /db/health.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function health( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->service->health(), 200 );
	}

	/**
	 * GET /db/autoload.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function autoload( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->service->autoload_audit(), 200 );
	}

	/**
	 * POST /db/cleanup/{tool}.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cleanup( WP_REST_Request $request ) {
		$tool = (string) $request->get_param( 'tool' );

		if ( ! in_array( $tool, self::ALLOWED_TOOLS, true ) ) {
			return new WP_Error(
				'feather_unknown_tool',
				__( 'Unknown database tool.', 'feather-performance' ),
				array( 'status' => 400 )
			);
		}

		switch ( $tool ) {
			case DbToolsService::TOOL_TRANSIENTS:
				$result = $this->service->cleanup_transients();
				break;
			case DbToolsService::TOOL_ELEMENTOR_REVISIONS:
				$result = $this->service->cleanup_elementor_revisions();
				break;
			case DbToolsService::TOOL_OEMBED_CACHE:
				$result = $this->service->cleanup_oembed();
				break;
			default:
				$result = array( 'deleted' => 0 );
		}

		// Refresh the health snapshot so the client doesn't need a second call.
		$result['health'] = $this->service->health();

		return new WP_REST_Response( $result, 200 );
	}
}
