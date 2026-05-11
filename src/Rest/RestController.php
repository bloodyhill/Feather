<?php
/**
 * REST controller — registers the feather/v1 namespace.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every endpoint that lives under feather/v1.
 *
 * Endpoints implement the `RouteRegistrar` interface and self-register
 * via `register_routes()` so adding a new endpoint is one filter call.
 */
final class RestController {

	public const NAMESPACE = 'feather/v1';

	/**
	 * Endpoints to register.
	 *
	 * @var RouteRegistrar[]
	 */
	private $endpoints;

	/**
	 * Constructor.
	 *
	 * @param RouteRegistrar[] $endpoints Endpoints.
	 */
	public function __construct( array $endpoints ) {
		$this->endpoints = $endpoints;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register every endpoint's routes against the WP REST API.
	 */
	public function register_routes(): void {
		foreach ( $this->endpoints as $endpoint ) {
			if ( $endpoint instanceof RouteRegistrar ) {
				$endpoint->register_routes( self::NAMESPACE );
			}
		}
	}
}
