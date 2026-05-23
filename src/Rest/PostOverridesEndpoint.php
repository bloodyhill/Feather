<?php
/**
 * REST endpoints for per-post feature overrides.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
use Feather\FeatureRegistry\FeatureRegistry;
use Feather\PostOverrides\OverridesRepository;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Surface for the React dashboard's per-page override management UI.
 *
 *   GET  /posts/{id}/overrides   — list disabled feature ids for one post
 *   POST /posts/{id}/overrides   — replace the disabled feature ids list
 */
final class PostOverridesEndpoint implements RouteRegistrar {

	/**
	 * Overrides repository.
	 *
	 * @var OverridesRepository
	 */
	private $repository;

	/**
	 * Feature registry — used to reject unknown feature ids.
	 *
	 * @var FeatureRegistry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param OverridesRepository $repository Repository.
	 * @param FeatureRegistry     $registry   Feature registry.
	 */
	public function __construct( OverridesRepository $repository, FeatureRegistry $registry ) {
		$this->repository = $repository;
		$this->registry   = $registry;
	}

	public function register_routes( string $namespace ): void {
		$permission = array( $this, 'permission_check' );

		register_rest_route(
			$namespace,
			'/posts/(?P<id>\d+)/overrides',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'read' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'write' ),
					'permission_callback' => $permission,
					'args'                => array(
						'disabled' => array(
							'type'     => 'array',
							'required' => true,
						),
					),
				),
			)
		);
	}

	public function permission_check( WP_REST_Request $request ): bool {
		if ( ! Capability::user_can() ) {
			return false;
		}
		$post_id = (int) $request->get_param( 'id' );
		return $post_id <= 0 || current_user_can( 'edit_post', $post_id );
	}

	/**
	 * GET /posts/{id}/overrides.
	 */
	public function read( WP_REST_Request $request ): WP_REST_Response {
		$post_id  = (int) $request->get_param( 'id' );
		$disabled = $this->repository->disabled_for_post( $post_id );
		return new WP_REST_Response(
			array(
				'post_id'  => $post_id,
				'disabled' => $disabled,
			),
			200
		);
	}

	/**
	 * POST /posts/{id}/overrides.
	 */
	public function write( WP_REST_Request $request ): WP_REST_Response {
		$post_id = (int) $request->get_param( 'id' );
		$raw     = $request->get_param( 'disabled' );

		$this->registry->load();

		$ids = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $candidate ) {
				if ( is_string( $candidate ) && $this->registry->has( $candidate ) ) {
					$ids[] = $candidate;
				}
			}
		}

		$this->repository->set_disabled_for_post( $post_id, $ids );

		return new WP_REST_Response(
			array(
				'post_id'  => $post_id,
				'disabled' => $this->repository->disabled_for_post( $post_id ),
			),
			200
		);
	}
}
