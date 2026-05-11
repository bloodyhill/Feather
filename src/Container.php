<?php
/**
 * Feather service container.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal PSR-11-flavored service container.
 *
 * Stores either ready-made instances (set/get) or lazy factories
 * (singleton/get). Resolves into a singleton on first get(); subsequent
 * get() calls return the cached instance.
 *
 * Intentionally tiny — no auto-wiring, no parameter resolution. The
 * Plugin class registers each service explicitly.
 */
final class Container {

	/**
	 * Resolved instances keyed by service id.
	 *
	 * @var array<string, object>
	 */
	private $instances = array();

	/**
	 * Lazy factories keyed by service id.
	 *
	 * @var array<string, callable>
	 */
	private $factories = array();

	/**
	 * Register a lazy factory. The first get() invokes it; subsequent
	 * get() calls return the cached instance.
	 *
	 * @param string   $id      Service id (typically a fully qualified class name).
	 * @param callable $factory Factory receiving the container; returns the instance.
	 */
	public function singleton( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Register a ready-made instance.
	 *
	 * @param string $id       Service id.
	 * @param object $instance Already-constructed object.
	 */
	public function set( string $id, object $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Retrieve a service by id.
	 *
	 * @param string $id Service id.
	 * @return object
	 *
	 * @throws \RuntimeException If $id is not registered.
	 */
	public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( isset( $this->factories[ $id ] ) ) {
			$factory  = $this->factories[ $id ];
			$instance = $factory( $this );
			if ( ! is_object( $instance ) ) {
				throw new \RuntimeException(
					sprintf(
						/* translators: %s service id */
						esc_html__( 'Factory for service "%s" did not return an object.', 'feather-performance' ),
						$id
					)
				);
			}
			$this->instances[ $id ] = $instance;
			unset( $this->factories[ $id ] );
			return $instance;
		}

		throw new \RuntimeException(
			sprintf(
				/* translators: %s service id */
				esc_html__( 'Service "%s" is not registered.', 'feather-performance' ),
				$id
			)
		);
	}

	/**
	 * Whether a service id is registered (resolved or pending).
	 *
	 * @param string $id Service id.
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] ) || isset( $this->factories[ $id ] );
	}
}
