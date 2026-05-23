<?php
/**
 * Schema migrations for Feather's custom tables.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Settings;

defined( 'ABSPATH' ) || exit;

// SettingsRepository lives in the same namespace; no `use` import needed.

/**
 * Idempotent dbDelta runner.
 *
 * Tracks the schema version in the `feather_schema_version` option so we
 * skip the (cheap but not free) dbDelta call on every plugins_loaded once
 * the database is at the current version.
 */
final class SchemaMigrator {

	public const VERSION_OPTION = 'feather_schema_version';
	public const SCHEMA_VERSION = 2;

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

		// Data migrations between schema versions. dbDelta handles the table
		// shape; this branch reshapes Feather's own option payload.
		if ( $current < 2 ) {
			$this->migrate_settings_to_v2();
		}

		// Only stamp the version when the schema actually landed. If it
		// didn't, leave the option alone so the next pageload retries.
		if ( $this->required_tables_exist() ) {
			update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, true );
		}
	}

	/**
	 * v1 → v2 settings reshape.
	 *
	 * Consolidates the three Elementor experiment-forcing feature toggles
	 * (force_experiments / force_extra_experiments / element_cache) into a
	 * single `f.elementor.experiments` feature id. Any of the three being on
	 * carries over to the consolidated feature; the corresponding sub-toggle
	 * is preserved in advanced.experiments so the optimizers it instantiates
	 * match the previous behavior.
	 */
	private function migrate_settings_to_v2(): void {
		$raw = get_option( SettingsRepository::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			// Truly fresh install — nothing to reshape. seed_feature_defaults()
			// in the Plugin orchestrator will populate the new ids when the
			// registry loads.
			return;
		}

		$features = isset( $raw['features'] ) && is_array( $raw['features'] ) ? $raw['features'] : array();

		$legacy = array(
			'f.elementor.force_experiments'       => 'svg_and_markup',
			'f.elementor.force_extra_experiments' => 'extra',
			'f.elementor.element_cache'           => 'element_cache',
		);

		$any_on             = false;
		$any_legacy_present = false;
		$sub                = array(
			'svg_and_markup' => false,
			'extra'          => false,
			'element_cache'  => false,
		);

		foreach ( $legacy as $legacy_id => $sub_key ) {
			if ( array_key_exists( $legacy_id, $features ) ) {
				$any_legacy_present = true;
				if ( ! empty( $features[ $legacy_id ] ) ) {
					$any_on          = true;
					$sub[ $sub_key ] = true;
				}
			}
			unset( $features[ $legacy_id ] );
		}

		// Only seed the consolidated id when we have an upgrade path to honor.
		// Truly fresh installs (no legacy ids ever saved) leave the slot empty
		// and let seed_feature_defaults() pick it up with the registry default.
		if ( $any_legacy_present && ! array_key_exists( 'f.elementor.experiments', $features ) ) {
			$features['f.elementor.experiments'] = $any_on;
		}

		$raw['features'] = $features;
		if ( ! isset( $raw['advanced'] ) || ! is_array( $raw['advanced'] ) ) {
			$raw['advanced'] = array();
		}
		$raw['advanced']['experiments'] = $sub;
		$raw['schema_version']          = SettingsRepository::SCHEMA_VERSION;

		update_option( SettingsRepository::OPTION_KEY, $raw, true );
		wp_cache_delete( SettingsRepository::OPTION_KEY, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
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
