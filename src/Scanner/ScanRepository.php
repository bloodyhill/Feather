<?php
/**
 * Read/write Feather's scan results table.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the `wp_feather_scan` table. Persists ScanResult rows, reads them
 * back, and produces the site-wide aggregate verdicts the FeatureGate
 * consults when deciding whether scan-gated features are safe.
 *
 * Aggregates are cached in the `feather_aggregated_scan_verdicts` option
 * because they are read on every plugins_loaded hook the gate runs.
 */
final class ScanRepository {

	public const AGGREGATE_OPTION = 'feather_aggregated_scan_verdicts';

	/**
	 * Cached aggregate verdicts.
	 *
	 * @var array<string, mixed>|null
	 */
	private $aggregate_cache = null;

	/**
	 * Fully qualified table name.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'feather_scan';
	}

	/**
	 * Whether the scan table is actually present.
	 *
	 * `SHOW TABLES LIKE` is translated by the SQLite Database Integration
	 * plugin to a `sqlite_master` lookup, so the same call works on both
	 * MySQL and SQLite installs.
	 */
	public function table_exists(): bool {
		global $wpdb;
		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return is_string( $found ) && $found === $table;
	}

	/**
	 * Decoded asset_handles list for a single post id, or null when no row
	 * has been scanned yet (caller must distinguish "no data" from "empty").
	 *
	 * @return string[]|null
	 */
	public function handles_for_post( int $post_id ): ?array {
		if ( $post_id <= 0 ) {
			return null;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT asset_handles FROM {$wpdb->prefix}feather_scan WHERE post_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id
			)
		);

		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$decoded = $this->safe_decode( $raw );
		$handles = array();
		foreach ( $decoded as $handle ) {
			if ( is_string( $handle ) && '' !== $handle ) {
				$handles[] = $handle;
			}
		}
		return $handles;
	}

	/**
	 * Persist a single scan result, overwriting any existing row for the post.
	 *
	 * Returns false when the underlying write fails — callers use this to
	 * detect a missing/unwritable table and surface a hard error rather
	 * than reporting a successful but empty scan.
	 *
	 * @param ScanResult $result   Result to persist.
	 * @param string     $modified GMT modified-at of the post at scan time.
	 */
	public function save( ScanResult $result, string $modified = '' ): bool {
		global $wpdb;

		$row = array(
			'post_id'          => $result->post_id(),
			'widget_types'     => (string) wp_json_encode( $result->widget_types() ),
			'asset_handles'    => (string) wp_json_encode( $result->asset_handles() ),
			'settings_flags'   => (string) wp_json_encode( $result->settings_flags() ),
			'scanned_at'       => gmdate( 'Y-m-d H:i:s' ),
			'modified_at_scan' => '' !== $modified ? $modified : null,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}feather_scan WHERE post_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$result->post_id()
			)
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$write_result = $wpdb->update(
				$this->table(),
				$row,
				array( 'id' => (int) $existing ),
				array( '%d', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$write_result = $wpdb->insert(
				$this->table(),
				$row,
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		// $wpdb returns false on hard failure (e.g. missing table). An UPDATE
		// against an unchanged row legitimately returns 0; treat anything that
		// isn't explicitly false as a successful write.
		$ok = ( false !== $write_result );

		if ( $ok ) {
			$this->invalidate_aggregates();
		}

		return $ok;
	}

	/**
	 * Read all rows in pages.
	 *
	 * @param int $page     1-indexed.
	 * @param int $per_page Page size.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function paginate( int $page, int $per_page ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, min( 200, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}feather_scan" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, widget_types, asset_handles, settings_flags, scanned_at FROM {$wpdb->prefix}feather_scan ORDER BY scanned_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$rows = is_array( $rows ) ? $rows : array();
		foreach ( $rows as &$row ) {
			$row['widget_types']   = $this->safe_decode( $row['widget_types'] ?? '' );
			$row['asset_handles']  = $this->safe_decode( $row['asset_handles'] ?? '' );
			$row['settings_flags'] = $this->safe_decode( $row['settings_flags'] ?? '' );
			$row['post_id']        = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
		}
		unset( $row );

		// $rows local var is mutated above; cast to indexed list.
		return array(
			'rows'  => array_values( $rows ),
			'total' => $total,
		);
	}

	/**
	 * Compute (and cache) site-wide verdicts.
	 *
	 * @return array<string, mixed>
	 */
	public function aggregate(): array {
		if ( null !== $this->aggregate_cache ) {
			return $this->aggregate_cache;
		}

		$cached = get_option( self::AGGREGATE_OPTION, null );
		if ( is_array( $cached ) ) {
			$this->aggregate_cache = $cached;
			return $cached;
		}

		$this->aggregate_cache = $this->compute_aggregate();
		update_option( self::AGGREGATE_OPTION, $this->aggregate_cache, true );
		return $this->aggregate_cache;
	}

	/**
	 * Compute aggregates by reading the table.
	 *
	 * @return array<string, mixed>
	 */
	private function compute_aggregate(): array {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}feather_scan" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = array(
			'total_pages'           => $count,
			'has_results'           => $count > 0,
			'eicons_usage_count'    => 0,
			'fa_icons_usage_count'  => 0,
			'google_fonts_usage_count' => 0,
			'lottie_usage_count'    => 0,
			'widget_counts'         => array(),
			'asset_counts'          => array(),
			'last_scanned_at'       => null,
		);

		if ( 0 === $count ) {
			return $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT widget_types, asset_handles, settings_flags, scanned_at FROM {$wpdb->prefix}feather_scan", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $rows ) ) {
			return $result;
		}

		foreach ( $rows as $row ) {
			$widgets = $this->safe_decode( $row['widget_types'] ?? '' );
			$assets  = $this->safe_decode( $row['asset_handles'] ?? '' );
			$flags   = $this->safe_decode( $row['settings_flags'] ?? '' );

			foreach ( $widgets as $widget ) {
				if ( ! is_string( $widget ) ) {
					continue;
				}
				$result['widget_counts'][ $widget ] = ( $result['widget_counts'][ $widget ] ?? 0 ) + 1;
			}
			foreach ( $assets as $asset ) {
				if ( ! is_string( $asset ) ) {
					continue;
				}
				$result['asset_counts'][ $asset ] = ( $result['asset_counts'][ $asset ] ?? 0 ) + 1;
			}

			if ( ! empty( $flags['uses_eicons'] ) ) {
				++$result['eicons_usage_count'];
			}
			if ( ! empty( $flags['uses_fa_icons'] ) ) {
				++$result['fa_icons_usage_count'];
			}
			if ( ! empty( $flags['uses_google_fonts'] ) ) {
				++$result['google_fonts_usage_count'];
			}
			if ( ! empty( $flags['uses_lottie'] ) ) {
				++$result['lottie_usage_count'];
			}

			if ( isset( $row['scanned_at'] ) ) {
				$ts = strtotime( (string) $row['scanned_at'] );
				if ( false !== $ts && ( null === $result['last_scanned_at'] || $ts > $result['last_scanned_at'] ) ) {
					$result['last_scanned_at'] = $ts;
				}
			}
		}

		return $result;
	}

	/**
	 * Drop the cached aggregate so it recomputes next read.
	 */
	public function invalidate_aggregates(): void {
		$this->aggregate_cache = null;
		delete_option( self::AGGREGATE_OPTION );
	}

	/**
	 * Drop every row.
	 */
	public function truncate(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}feather_scan" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->invalidate_aggregates();
	}

	/**
	 * Decode a JSON string, returning [] for non-array results.
	 *
	 * @param mixed $raw JSON or anything else.
	 * @return array<int|string, mixed>
	 */
	private function safe_decode( $raw ): array {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
