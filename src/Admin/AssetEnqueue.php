<?php
/**
 * Enqueue the React admin bundle and bootstrap data.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Admin;

use Feather\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Loads `assets/admin/index.js` + `index.css` on every Feather admin page,
 * and injects a JSON bootstrap payload so React can pick up the current
 * route, theme, REST root, and nonce without an extra round-trip.
 */
final class AssetEnqueue {

	public const HANDLE = 'feather-admin';

	/**
	 * Admin menu (used for hook suffixes + route resolution).
	 *
	 * @var AdminMenu
	 */
	private $admin_menu;

	/**
	 * Settings repository (used to read current theme).
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param AdminMenu          $admin_menu Admin menu service.
	 * @param SettingsRepository $settings   Settings repository.
	 */
	public function __construct( AdminMenu $admin_menu, SettingsRepository $settings ) {
		$this->admin_menu = $admin_menu;
		$this->settings   = $settings;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Enqueue assets only on Feather's admin screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function maybe_enqueue( $hook ): void {
		if ( ! is_string( $hook ) ) {
			return;
		}
		if ( ! in_array( $hook, $this->admin_menu->hook_suffixes(), true ) ) {
			return;
		}

		$asset_manifest = FEATHER_DIR . 'assets/admin/index.asset.php';
		$js_url         = FEATHER_URL . 'assets/admin/index.js';
		$css_path       = FEATHER_DIR . 'assets/admin/index.css';
		$css_url        = FEATHER_URL . 'assets/admin/index.css';
		$rtl_css_path   = FEATHER_DIR . 'assets/admin/index-rtl.css';

		$dependencies = array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-data' );
		$version      = FEATHER_VERSION;

		if ( file_exists( $asset_manifest ) ) {
			$asset = require $asset_manifest;
			if ( is_array( $asset ) ) {
				if ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) {
					$dependencies = $asset['dependencies'];
				}
				if ( isset( $asset['version'] ) && is_string( $asset['version'] ) ) {
					$version = $asset['version'];
				}
			}
		}

		wp_enqueue_script(
			self::HANDLE,
			$js_url,
			$dependencies,
			$version,
			true
		);

		wp_set_script_translations( self::HANDLE, 'feather-performance' );

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				self::HANDLE,
				$css_url,
				array( 'wp-components' ),
				$version
			);
			if ( file_exists( $rtl_css_path ) ) {
				wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
			}
		}

		wp_add_inline_script(
			self::HANDLE,
			'window.feather = window.feather || {}; window.feather.boot = ' . wp_json_encode( $this->bootstrap_payload() ) . ';',
			'before'
		);
	}

	/**
	 * Data the React app needs at boot.
	 *
	 * @return array<string, mixed>
	 */
	private function bootstrap_payload(): array {
		$user = wp_get_current_user();

		$ink_logo   = file_exists( FEATHER_DIR . 'assets/img/feather-mark-ink.png' )
			? esc_url_raw( FEATHER_URL . 'assets/img/feather-mark-ink.png' )
			: '';
		$cream_logo = file_exists( FEATHER_DIR . 'assets/img/feather-mark-cream.png' )
			? esc_url_raw( FEATHER_URL . 'assets/img/feather-mark-cream.png' )
			: '';
		$brand_logo = file_exists( FEATHER_DIR . 'assets/icons/logo-20200933.webp' )
			? esc_url_raw( FEATHER_URL . 'assets/icons/logo-20200933.webp' )
			: '';

		return array(
			'version'      => FEATHER_VERSION,
			'restRoot'     => esc_url_raw( rest_url( 'feather/v1' ) ),
			'restNonce'    => wp_create_nonce( 'wp_rest' ),
			'siteUrl'      => esc_url_raw( home_url( '/' ) ),
			'adminUrl'     => esc_url_raw( admin_url() ),
			'locale'       => determine_locale(),
			'isMultisite'  => is_multisite(),
			'currentRoute' => $this->admin_menu->current_route(),
			'routeMap'     => $this->admin_menu->route_map(),
			'theme'        => (string) $this->settings->get( 'theme', SettingsRepository::THEME_SYSTEM ),
			'logos'        => array(
				'ink'   => $ink_logo,
				'cream' => $cream_logo,
				'brand' => $brand_logo,
			),
			'user'         => array(
				'id'          => $user instanceof \WP_User ? (int) $user->ID : 0,
				'displayName' => $user instanceof \WP_User ? $user->display_name : '',
			),
		);
	}
}
