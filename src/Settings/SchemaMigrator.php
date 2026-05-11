<?php
/**
 * Schema migrations for Feather's custom tables.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Idempotent dbDelta runner.
 *
 * Tracks the schema version in the `feather_schema_version` option so we
 * skip the (cheap but not free) dbDelta call on every plugins_loaded once
 * the database is at the current version.
 */
final class SchemaMigrator {

	public const VERSION_OPTION = 'feather_schema_version';
	public const SCHEMA_VERSION = 1;

	/**
	 * Run migrations if the stored version is behind OR if the required
	 * tables don't actually exist.
	 *
	 * The tables-exist check protects against the case where a prior
	 * dbDelta silently failed (most often on SQLite-backed installs where
	 * the translator chokes on a particular DDL construct) and the version
	 * option got bumped anyway. Without this re-check, the scanner would
	 * cheerfully report "N pages scanned" while every insert silently
	 * returns false.
	 */
	public function migrate(): void {
		$current = (int) get_option( self::VERSION_OPTION, 0 );
		if ( $current >= self::SCHEMA_VERSION && $this->required_tables_exist() ) {
			return;
		}

		$this->run_dbdelta();

		// Only stamp the version when the schema actually landed. If it
		// didn't, leave the option alone so the next pageload retries.
		if ( $this->required_tables_exist() ) {
			update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, true );
		}
	}

	/**
	 * Whether every table this migrator owns is present in the database.
	 *
	 * Uses `SHOW TABLES LIKE` because the WP SQLite Database Integration
	 * plugin translates that to a `sqlite_master` lookup, making the same
	 * call portable across MySQL and SQLite installs.
	 */
	public function required_tables_exist(): bool {
		global $wpdb;

		$required = array(
			$wpdb->prefix . 'feather_scan',
			$wpdb->prefix . 'feather_metrics',
		);

		foreach ( $required as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! is_string( $found ) || $found !== $table ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Force re-running dbDelta regardless of stored version. Useful for
	 * activation hooks where we want belt-and-braces table creation.
	 */
	public function run_dbdelta(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$scan_table      = $wpdb->prefix . 'feather_scan';
		$metrics_table   = $wpdb->prefix . 'feather_metrics';

		$scan_sql = "CREATE TABLE {$scan_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			widget_types longtext NOT NULL,
			asset_handles longtext NOT NULL,
			settings_flags longtext NOT NULL,
			scanned_at datetime NOT NULL,
			modified_at_scan datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			KEY scanned_at (scanned_at)
		) {$charset_collate};";

		$metrics_sql = "CREATE TABLE {$metrics_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			metric_type varchar(40) NOT NULL,
			url varchar(255) NOT NULL,
			payload longtext NOT NULL,
			recorded_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY metric_type (metric_type),
			KEY recorded_at (recorded_at)
		) {$charset_collate};";

		dbDelta( $scan_sql );
		dbDelta( $metrics_sql );
	}
}
