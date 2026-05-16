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
		add_filter( 'rest_post_dispatch', array( $this, 'add_no_cache_headers' ), 10, 3 );
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

	/**
	 * Tag every feather/v1 response with strict no-cache headers.
	 *
	 * Some page caches (LiteSpeed Cache, WP Rocket, hosting-level Varnish/
	 * Cloudflare rules) cache REST responses by URL even for authenticated
	 * requests, which makes settings, scan state, and metrics appear stale
	 * after toggles until the cache TTL expires. Forcing no-store on our
	 * namespace prevents the listing UI from picking up cached responses.
	 *
	 * @param \WP_HTTP_Response $response Response to send.
	 * @param \WP_REST_Server   $server   Server instance.
	 * @param \WP_REST_Request  $request  Request instance.
	 * @return \WP_HTTP_Response
	 */
	public function add_no_cache_headers( $response, $server, $request ) {
		unset( $server );
		if ( ! ( $response instanceof \WP_HTTP_Response ) || ! ( $request instanceof \WP_REST_Request ) ) {
			return $response;
		}
		$route = (string) $request->get_route();
		if ( 0 !== strpos( $route, '/' . self::NAMESPACE . '/' ) && '/' . self::NAMESPACE !== $route ) {
			return $response;
		}
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', '0' );
		return $response;
	}
}
