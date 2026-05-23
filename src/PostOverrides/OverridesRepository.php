<?php
/**
 * Per-post feature override storage.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\PostOverrides;

defined( 'ABSPATH' ) || exit;

/**
 * Reads/writes the per-post "disabled features" list stored as postmeta.
 *
 * Semantics: an override only *suppresses* a feature on a single request —
 * it never *enables* a feature that's globally off. Sitewide gate logic
 * (FeatureGate / ScanRepository aggregates) is unaffected by overrides;
 * a feature stays "unsafe to enable" if the scan says so, regardless of
 * which pages opt out of it.
 *
 * Storage shape:
 *   meta_key   = _feather_disabled_features
 *   meta_value = JSON-encoded string array of feature ids (e.g. ["f.media.iframe_lazy"])
 */
final class OverridesRepository {

	public const META_KEY = '_feather_disabled_features';

	/**
	 * Per-request memo so the same post id resolves once per request.
	 *
	 * @var array<int, string[]>
	 */
	private $cache = array();

	/**
	 * Disabled feature ids for the given post id, or empty array.
	 *
	 * @param int $post_id Post id.
	 * @return string[]
	 */
	public function disabled_for_post( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}
		if ( isset( $this->cache[ $post_id ] ) ) {
			return $this->cache[ $post_id ];
		}

		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			$this->cache[ $post_id ] = array();
			return array();
		}
		$decoded = json_decode( $raw, true );
		$ids     = array();
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $id ) {
				if ( is_string( $id ) && '' !== $id ) {
					$ids[] = $id;
				}
			}
		}
		$ids                     = array_values( array_unique( $ids ) );
		$this->cache[ $post_id ] = $ids;
		return $ids;
	}

	/**
	 * Whether the given feature is disabled for the given post id.
	 *
	 * @param int    $post_id    Post id.
	 * @param string $feature_id Feature id.
	 */
	public function is_disabled_for_post( int $post_id, string $feature_id ): bool {
		if ( $post_id <= 0 || '' === $feature_id ) {
			return false;
		}
		return in_array( $feature_id, $this->disabled_for_post( $post_id ), true );
	}

	/**
	 * Replace the disabled-features list for a post.
	 *
	 * @param int      $post_id     Post id.
	 * @param string[] $feature_ids Feature ids to disable on this page.
	 */
	public function set_disabled_for_post( int $post_id, array $feature_ids ): void {
		if ( $post_id <= 0 ) {
			return;
		}

		$clean = array();
		foreach ( $feature_ids as $id ) {
			if ( is_string( $id ) && '' !== $id && 1 === preg_match( '/^f\.[a-z0-9_]+\.[a-z0-9_]+$/', $id ) ) {
				$clean[] = $id;
			}
		}
		$clean                   = array_values( array_unique( $clean ) );
		$this->cache[ $post_id ] = $clean;

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $clean ) );
	}

	/**
	 * Whether the given feature is disabled on the currently queried page.
	 *
	 * Returns false outside the main query (admin, REST, cron, CLI).
	 *
	 * @param string $feature_id Feature id.
	 */
	public function is_disabled_for_current_request( string $feature_id ): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( ! function_exists( 'get_queried_object_id' ) ) {
			return false;
		}
		$queried = (int) get_queried_object_id();
		if ( $queried <= 0 ) {
			return false;
		}
		return $this->is_disabled_for_post( $queried, $feature_id );
	}
}
