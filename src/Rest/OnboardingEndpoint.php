<?php
/**
 * REST endpoints for the welcome banner.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
use Feather\Onboarding\OnboardingState;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Surface for the Dashboard's onboarding banner.
 *
 *   GET  /onboarding/state    — current state
 *   POST /onboarding/state    — update state (pending|scanned|completed)
 */
final class OnboardingEndpoint implements RouteRegistrar {

	/**
	 * State.
	 *
	 * @var OnboardingState
	 */
	private $state;

	/**
	 * Constructor.
	 *
	 * @param OnboardingState $state State.
	 */
	public function __construct( OnboardingState $state ) {
		$this->state = $state;
	}

	public function register_routes( string $namespace ): void {
		$permission = array( $this, 'permission_check' );

		register_rest_route(
			$namespace,
			'/onboarding/state',
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
						'state' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'enum'              => array(
								OnboardingState::STATE_PENDING,
								OnboardingState::STATE_SCANNED,
								OnboardingState::STATE_COMPLETED,
							),
						),
					),
				),
			)
		);
	}

	public function permission_check(): bool {
		return Capability::user_can();
	}

	/**
	 * GET /onboarding/state.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function read( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return new WP_REST_Response( $this->state->to_array(), 200 );
	}

	/**
	 * POST /onboarding/state.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function write( WP_REST_Request $request ): WP_REST_Response {
		$next = (string) $request->get_param( 'state' );
		$this->state->set_state( $next );
		return new WP_REST_Response( $this->state->to_array(), 200 );
	}
}
