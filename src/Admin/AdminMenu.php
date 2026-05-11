<?php
/**
 * Feather admin menu pages.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the top-level Feather menu and its submenu pages.
 *
 * Each submenu is its own page slug so the WP admin sidebar reflects the
 * active section (highlighted submenu, breadcrumb, etc.). Every page renders
 * the same React mount point — the bootstrap payload tells the React app
 * which initial route to mount.
 */
final class AdminMenu {

	public const PAGE_SLUG = 'feather-performance';

	/**
	 * Submenu definitions: [ page_slug => [ label, route_id ] ].
	 *
	 * @var array<string, array{label: string, route: string}>
	 */
	private $submenus = array();

	/**
	 * Hook suffixes WordPress assigned to each registered page. Used by
	 * AssetEnqueue to know which screens should load the React bundle.
	 *
	 * @var string[]
	 */
	private $hook_suffixes = array();

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_pages' ) );
	}

	/**
	 * Build the submenu definitions and register every page.
	 */
	public function register_pages(): void {
		$this->submenus = array(
			self::PAGE_SLUG => array(
				'label' => __( 'Dashboard', 'feather-performance' ),
				'route' => 'dashboard',
			),
			'feather-features' => array(
				'label' => __( 'Features', 'feather-performance' ),
				'route' => 'features',
			),
			'feather-scan' => array(
				'label' => __( 'Site Scan', 'feather-performance' ),
				'route' => 'scan',
			),
			'feather-database' => array(
				'label' => __( 'Database', 'feather-performance' ),
				'route' => 'database',
			),
			'feather-settings' => array(
				'label' => __( 'Settings', 'feather-performance' ),
				'route' => 'settings',
			),
			'feather-about' => array(
				'label' => __( 'About', 'feather-performance' ),
				'route' => 'about',
			),
		);

		// Top-level page — uses the Feather mark when ink-mark.png is shipped,
		// otherwise falls back to a dashicon. The icon URL must be public.
		$icon = $this->menu_icon_url();

		$top = add_menu_page(
			__( 'Feather', 'feather-performance' ),
			__( 'Feather', 'feather-performance' ),
			Capability::MANAGE,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			$icon,
			81
		);
		if ( is_string( $top ) ) {
			$this->hook_suffixes[] = $top;
		}

		// First submenu replaces the auto-generated parent entry so the label
		// reads "Dashboard" instead of "Feather" twice.
		foreach ( $this->submenus as $slug => $entry ) {
			$hook = add_submenu_page(
				self::PAGE_SLUG,
				$entry['label'],
				$entry['label'],
				Capability::MANAGE,
				$slug,
				array( $this, 'render_page' )
			);
			if ( is_string( $hook ) ) {
				$this->hook_suffixes[] = $hook;
			}
		}
	}

	/**
	 * Hook suffixes for every Feather admin page.
	 *
	 * @return string[]
	 */
	public function hook_suffixes(): array {
		return $this->hook_suffixes;
	}

	/**
	 * Resolve the current page slug to its React route id.
	 *
	 * Reads `$_GET['page']` for the routing decision — no nonce verification
	 * is required because routing on a query parameter is non-destructive.
	 */
	public function current_route(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( isset( $this->submenus[ $page ] ) ) {
			return $this->submenus[ $page ]['route'];
		}
		return 'dashboard';
	}

	/**
	 * Map of page-slug → React route id, for the React Sidebar to build links.
	 *
	 * @return array<string, string>
	 */
	public function route_map(): array {
		$map = array();
		foreach ( $this->submenus as $slug => $entry ) {
			$map[ $slug ] = $entry['route'];
		}
		return $map;
	}

	/**
	 * Render the React mount point. Identical for every Feather page.
	 */
	public function render_page(): void {
		if ( ! Capability::user_can() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'feather-performance' ) );
		}

		echo '<div class="wrap" id="feather-admin-wrap">';
		echo '<div id="feather-admin"></div>';
		echo '<noscript><p>' . esc_html__( 'Feather requires JavaScript to be enabled in your browser.', 'feather-performance' ) . '</p></noscript>';
		echo '</div>';
	}

	/**
	 * Inline SVG data URI for the WP admin menu icon.
	 *
	 * Returning a data URI (vs a file URL) lets WordPress apply its native
	 * `.svg` icon styling — which uses currentColor and is correctly sized
	 * for the menu slot. This avoids the natural-size-overflow issue that
	 * raster icons can hit when a third-party plugin's CSS overrides WP's
	 * `.wp-menu-image img { width:20px }` rule.
	 *
	 * The full-color brand mark (`assets/icons/logo-20200933.webp`) ships
	 * with the plugin and is used inside the React UI where we have full
	 * CSS control — see the Dashboard welcome banner.
	 */
	private function menu_icon_url(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h11v2.5H8.5V11H15v2.5H8.5V20H6V4z"/><path d="M11 7.5l6-1.5v2l-6 1.5V7.5zm0 4.5l5-1.25v2L11 14V12z" opacity=".55"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
