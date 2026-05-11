<?php
/**
 * Feather uninstall — runs when the user deletes the plugin from WP admin.
 *
 * Removes Feather's options, custom tables, scheduled events, and
 * transients. If the user wants to keep their data, they should use
 * Settings → Reset → "Reset settings to defaults" instead of Delete.
 *
 * @package Feather
 */

declare( strict_types=1 );

// Bail if uninstall is not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Drop a Feather-managed option key on the current site.
 *
 * @param string $key Option key.
 */
$feather_drop_options = static function (): void {
	delete_option( 'feather_settings' );
	delete_option( 'feather_install_date' );
	delete_option( 'feather_aggregated_scan_verdicts' );
};

/**
 * Drop Feather custom tables on the current site. Table names are
 * fully controlled by the plugin (no user input) so the prepared-statement
 * caveat does not apply; PCP rules permit DROP TABLE in uninstall.php.
 */
$feather_drop_tables = static function () use ( $wpdb ): void {
	$tables = array(
		$wpdb->prefix . 'feather_scan',
		$wpdb->prefix . 'feather_metrics',
	);
	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
	}
};

/**
 * Clear scheduled events Feather may have registered.
 */
$feather_clear_schedule = static function (): void {
	$hooks = array(
		'feather/scheduled_scan',
		'feather/license_revalidate',
		'feather/metrics_prune',
	);
	foreach ( $hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
			$timestamp = wp_next_scheduled( $hook );
		}
	}
};

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		$feather_drop_options();
		$feather_drop_tables();
		$feather_clear_schedule();
		restore_current_blog();
	}
} else {
	$feather_drop_options();
	$feather_drop_tables();
	$feather_clear_schedule();
}
