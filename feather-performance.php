<?php
/**
 * Plugin Name:       Feather Performance
 * Plugin URI:        https://featherplugin.com/
 * Description:       Trims Elementor's asset load, throttles WordPress overhead, cleans the database, and surfaces per-page measurements — all from one dashboard.
 * Version:           0.2.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Feather
 * Author URI:        https://profiles.wordpress.org/featherr/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       feather-performance
 * Domain Path:       /languages
 *
 * Feather — performance plugin for Elementor sites.
 * Copyright (C) 2026  Feather
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @package Feather
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Plugin meta constants.
define( 'FEATHER_VERSION', '0.2.4' );
define( 'FEATHER_FILE', __FILE__ );
define( 'FEATHER_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEATHER_URL', plugin_dir_url( __FILE__ ) );
define( 'FEATHER_BASENAME', plugin_basename( __FILE__ ) );
define( 'FEATHER_MIN_PHP', '7.4' );
define( 'FEATHER_MIN_WP', '6.0' );

// Composer-free PSR-4 autoloader for the Feather\ namespace.
require_once FEATHER_DIR . 'src/Autoloader.php';

/**
 * Boot Feather once WordPress has loaded all plugins.
 *
 * Priority 5 means our `feather/register_addons` action (fired at
 * priority 20 inside Plugin::on_plugins_loaded) gives add-ons a clean
 * window to hook in.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		$container = new \Feather\Container();
		$plugin    = new \Feather\Plugin( $container );
		$container->set( \Feather\Plugin::class, $plugin );

		\Feather\Plugin::set_instance( $plugin );

		$plugin->boot();
	},
	5
);

/**
 * Activation hook: stamp the install date for future grandfathering, then
 * run the schema migrator so the custom tables exist before any feature
 * tries to write to them.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		if ( '' === (string) get_option( 'feather_install_date', '' ) ) {
			update_option( 'feather_install_date', gmdate( 'c' ), true );
		}
		( new \Feather\Settings\SchemaMigrator() )->migrate();
	}
);

/**
 * Deactivation hook: clear scheduled scanner ticks so a deactivated plugin
 * doesn't leave dangling cron events.
 */
register_deactivation_hook(
	__FILE__,
	static function (): void {
		$timestamp = wp_next_scheduled( 'feather/scan/tick' );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'feather/scan/tick' );
			$timestamp = wp_next_scheduled( 'feather/scan/tick' );
		}
	}
);

if ( ! function_exists( 'feather_plugin' ) ) {
	/**
	 * Global accessor for the Feather plugin instance.
	 *
	 * Returns null if called before plugins_loaded:5 has fired.
	 *
	 * @return \Feather\Plugin|null
	 */
	function feather_plugin(): ?\Feather\Plugin {
		return \Feather\Plugin::instance();
	}
}
