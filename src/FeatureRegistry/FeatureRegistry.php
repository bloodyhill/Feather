<?php
/**
 * Feature registry — single source of truth for every Feather feature.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\FeatureRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Loads static feature definitions and applies the `feather/feature_registry`
 * filter so add-ons (the future feather-pro plugin or third parties) can
 * register their own features through the same surface free uses.
 */
final class FeatureRegistry {

	/**
	 * Hydrated metadata keyed by feature id.
	 *
	 * @var array<string, FeatureMetadata>
	 */
	private $features = array();

	/**
	 * Whether load() has run.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * Load definitions from features.php and the registry filter.
	 *
	 * Idempotent — safe to call multiple times.
	 */
	public function load(): void {
		if ( $this->loaded ) {
			return;
		}

		$definitions_file = __DIR__ . '/features.php';
		$definitions      = is_readable( $definitions_file )
			? (array) require $definitions_file
			: array();

		/**
		 * Filter the master list of feature definitions before they are indexed.
		 *
		 * Each definition is an associative array matching the FeatureMetadata
		 * constructor shape. Add-ons should append rather than replace.
		 *
		 * @param array<int, array<string, mixed>> $definitions Raw definitions.
		 */
		$definitions = (array) apply_filters( 'feather/feature_registry', $definitions );

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['id'] ) ) {
				continue;
			}
			$metadata                          = new FeatureMetadata( $definition );
			$this->features[ $metadata->id() ] = $metadata;
		}

		$this->loaded = true;
	}

	/**
	 * Look up a feature by id.
	 *
	 * @param string $id Feature id.
	 */
	public function get( string $id ): ?FeatureMetadata {
		return $this->features[ $id ] ?? null;
	}

	/**
	 * Whether a feature is registered.
	 *
	 * @param string $id Feature id.
	 */
	public function has( string $id ): bool {
		return isset( $this->features[ $id ] );
	}

	/**
	 * Every registered feature.
	 *
	 * @return array<string, FeatureMetadata>
	 */
	public function all(): array {
		return $this->features;
	}

	/**
	 * Filter features by category.
	 *
	 * @param string $category Category slug (see FeatureMetadata constants).
	 * @return array<string, FeatureMetadata>
	 */
	public function by_category( string $category ): array {
		return array_filter(
			$this->features,
			static function ( FeatureMetadata $f ) use ( $category ): bool {
				return $f->category() === $category;
			}
		);
	}
}
