<?php
/**
 * REST endpoints for the site scanner.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
use Feather\Scanner\ScanRepository;
use Feather\Scanner\SiteScanner;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Surface for the React Scan screen.
 *
 *   POST /scan/start     — begin a new scan
 *   POST /scan/cancel    — abort an in-progress scan
 *   GET  /scan/status    — current state + progress
 *   GET  /scan/aggregate — site-wide widget/asset/flag counts
 *   GET  /scan/results   — paginated per-post results
 */
final class ScanEndpoint implements RouteRegistrar {

	/**
	 * Site scanner.
	 *
	 * @var SiteScanner
	 */
	private $scanner;

	/**
	 * Scan repository.
	 *
	 * @var ScanRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param SiteScanner    $scanner    Site scanner.
	 * @param ScanRepository $repository Repository.
	 */
	public function __construct( SiteScanner $scanner, ScanRepository $repository ) {
		$this->scanner    = $scanner;
		$this->repository = $repository;
	}

	public function register_routes( string $namespace ): void {
		$permission = array( $this, 'permission_check' );

		register_rest_route(
			$namespace,
			'/scan/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'start' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$namespace,
			'/scan/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$namespace,
			'/scan/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$namespace,
			'/scan/aggregate',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'aggregate' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$namespace,
			'/scan/results',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'results' ),
				'permission_callback' => $permission,
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/scan/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * POST /scan/clear — drop all scan results.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function clear( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$this->scanner->cancel();
		$this->repository->truncate();
		return new WP_REST_Response( array( 'cleared' => true ), 200 );
	}

	/**
	 * Permission gate.
	 */
	public function permission_check(): bool {
		return Capability::user_can();
	}

	/**
	 * POST /scan/start.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function start( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$state = $this->scanner->start();
		return new WP_REST_Response( $state->to_array(), 202 );
	}

	/**
	 * POST /scan/cancel.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function cancel( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$state = $this->scanner->cancel();
		return new WP_REST_Response( $state->to_array(), 200 );
	}

	/**
	 * GET /scan/status.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function status( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$state = $this->scanner->status();
		return new WP_REST_Response( $state->to_array(), 200 );
	}

	/**
	 * GET /scan/aggregate.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function aggregate( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->repository->aggregate(), 200 );
	}

	/**
	 * GET /scan/results.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function results( WP_REST_Request $request ): WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		$payload  = $this->repository->paginate( $page, $per_page );
		return new WP_REST_Response( $payload, 200 );
	}
}
