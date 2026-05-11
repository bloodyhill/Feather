<?php
/**
 * Endpoint contract.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Interface every endpoint implements so RestController can register them
 * in a uniform loop without knowing the concrete shapes.
 */
interface RouteRegistrar {

	/**
	 * Register this endpoint's routes against the given namespace.
	 *
	 * @param string $namespace REST namespace (e.g. "feather/v1").
	 */
	public function register_routes( string $namespace ): void;
}
