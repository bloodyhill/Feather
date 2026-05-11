<?php
/**
 * REST endpoints for page-weight measurement and history.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
use Feather\Metrics\MetricsRepository;
use Feather\Metrics\PageWeightProbe;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Surface for the Dashboard's metrics card.
 *
 *   POST /metrics/capture     — probe the homepage now, persist the snapshot
 *   GET  /metrics/latest      — most recent snapshot
 *   GET  /metrics/history     — last N snapshots (default 30)
 */
final class MetricsEndpoint implements RouteRegistrar {

	/**
	 * Probe.
	 *
	 * @var PageWeightProbe
	 */
	private $probe;

	/**
	 * Repository.
	 *
	 * @var MetricsRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param PageWeightProbe   $probe      Probe.
	 * @param MetricsRepository $repository Repository.
	 */
	public function __construct( PageWeightProbe $probe, MetricsRepository $repository ) {
		$this->probe      = $probe;
		$this->repository = $repository;
	}

	public function register_routes( string $namespace ): void {
		$permission = array( $this, 'permission_check' );

		register_rest_route(
			$namespace,
			'/metrics/capture',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'capture' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$namespace,
			'/metrics/latest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'latest' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$namespace,
			'/metrics/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'history' ),
				'permission_callback' => $permission,
				'args'                => array(
					'limit' => array(
						'type'              => 'integer',
						'required'          => false,
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/metrics/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * POST /metrics/clear.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function clear( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$this->repository->truncate();
		return new WP_REST_Response( array( 'cleared' => true ), 200 );
	}

	public function permission_check(): bool {
		return Capability::user_can();
	}

	/**
	 * POST /metrics/capture.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function capture( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$snapshot = $this->probe->probe_home();
		// Even on probe error we still record so the user sees what failed.
		$this->repository->save(
			MetricsRepository::METRIC_PAGE_WEIGHT,
			(string) ( $snapshot['url'] ?? home_url( '/' ) ),
			$snapshot
		);

		return new WP_REST_Response( $snapshot, 200 );
	}

	/**
	 * GET /metrics/latest.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function latest( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$row = $this->repository->latest( MetricsRepository::METRIC_PAGE_WEIGHT, home_url( '/' ) );
		return new WP_REST_Response( $row, 200 );
	}

	/**
	 * GET /metrics/history.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function history( WP_REST_Request $request ): WP_REST_Response {
		$limit = (int) $request->get_param( 'limit' );
		if ( $limit <= 0 ) {
			$limit = 30;
		}
		$rows = $this->repository->history(
			MetricsRepository::METRIC_PAGE_WEIGHT,
			home_url( '/' ),
			$limit
		);
		return new WP_REST_Response(
			array(
				'rows'  => $rows,
				'count' => count( $rows ),
			),
			200
		);
	}
}
