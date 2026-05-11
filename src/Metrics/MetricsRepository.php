<?php
/**
 * Read/write Feather's metrics history table.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Metrics;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the `wp_feather_metrics` table — schema lives in SchemaMigrator.
 *
 * Stores rows keyed by metric_type (e.g. "page_weight") + URL, with the
 * full snapshot serialized in `payload`. Capped at last 90 days; pruned
 * by `prune_old()`.
 */
final class MetricsRepository {

	public const METRIC_PAGE_WEIGHT = 'page_weight';

	public const HISTORY_DAYS = 90;
	public const HISTORY_LIMIT = 500;

	/**
	 * Fully qualified table name.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'feather_metrics';
	}

	/**
	 * Persist a snapshot.
	 *
	 * @param string               $metric_type Metric category constant.
	 * @param string               $url         URL the metric refers to.
	 * @param array<string, mixed> $payload     Snapshot data.
	 */
	public function save( string $metric_type, string $url, array $payload ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$this->table(),
			array(
				'metric_type' => $metric_type,
				'url'         => $url,
				'payload'     => (string) wp_json_encode( $payload ),
				'recorded_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Most-recent snapshot of a metric type for a URL, or null.
	 *
	 * @param string $metric_type Metric category.
	 * @param string $url         URL.
	 * @return array<string, mixed>|null
	 */
	public function latest( string $metric_type, string $url ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT payload, recorded_at FROM {$wpdb->prefix}feather_metrics WHERE metric_type = %s AND url = %s ORDER BY recorded_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$metric_type,
				$url
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
		if ( ! is_array( $payload ) ) {
			return null;
		}
		$payload['recorded_at'] = isset( $row['recorded_at'] ) ? (string) $row['recorded_at'] : '';
		return $payload;
	}

	/**
	 * History of a metric type for a URL, oldest-first.
	 *
	 * @param string $metric_type Metric category.
	 * @param string $url         URL.
	 * @param int    $limit       Max rows to return.
	 * @return array<int, array<string, mixed>>
	 */
	public function history( string $metric_type, string $url, int $limit = 30 ): array {
		global $wpdb;

		$limit = max( 1, min( self::HISTORY_LIMIT, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT payload, recorded_at FROM {$wpdb->prefix}feather_metrics WHERE metric_type = %s AND url = %s ORDER BY recorded_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$metric_type,
				$url,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( array_reverse( $rows ) as $row ) {
			$payload = json_decode( (string) ( $row['payload'] ?? '' ), true );
			if ( ! is_array( $payload ) ) {
				continue;
			}
			$payload['recorded_at'] = isset( $row['recorded_at'] ) ? (string) $row['recorded_at'] : '';
			$out[]                  = $payload;
		}

		return $out;
	}

	/**
	 * Drop every metrics row.
	 */
	public function truncate(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}feather_metrics" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Drop snapshots older than HISTORY_DAYS.
	 */
	public function prune_old(): void {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::HISTORY_DAYS * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}feather_metrics WHERE recorded_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);
	}
}
