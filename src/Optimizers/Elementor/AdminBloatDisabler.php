<?php
/**
 * Suppress Elementor's admin bloat: dashboard widget, notices, promo upsells,
 * and the over-aggressive editor autosave.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Multiple small admin tweaks bundled together because they share a single
 * user intent ("get Elementor's noise out of my admin"). If finer-grained
 * control is later requested, this class can be split into siblings.
 */
final class AdminBloatDisabler extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.admin_bloat';
	}

	public function apply(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'remove_dashboard_widget' ), 40 );
		add_action( 'admin_init', array( $this, 'remove_admin_notices' ), 100 );

		add_filter( 'elementor/admin/menu/promotions', '__return_empty_array' );
		add_filter( 'elementor/editor/localize_settings', array( $this, 'lengthen_autosave' ) );
	}

	/**
	 * Remove Elementor's "Overview" widget from the WP dashboard.
	 */
	public function remove_dashboard_widget(): void {
		remove_meta_box( 'e-dashboard-overview', 'dashboard', 'normal' );
	}

	/**
	 * Detach Elementor's promotional / What's-New admin notices.
	 */
	public function remove_admin_notices(): void {
		remove_action( 'admin_notices', 'elementor_fail_to_load_notice' );

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		$elementor = \Elementor\Plugin::$instance ?? null;
		if ( null === $elementor || empty( $elementor->admin ) || ! is_object( $elementor->admin ) ) {
			return;
		}

		if ( ! method_exists( $elementor->admin, 'get_component' ) ) {
			return;
		}

		$notices = $elementor->admin->get_component( 'admin-notices' );
		if ( is_object( $notices ) && method_exists( $notices, 'admin_notices' ) ) {
			remove_action( 'admin_notices', array( $notices, 'admin_notices' ) );
		}
	}

	/**
	 * Stretch Elementor's editor autosave from the default 60s to 5 min.
	 *
	 * @param mixed $settings Localized editor settings.
	 * @return mixed
	 */
	public function lengthen_autosave( $settings ) {
		if ( is_array( $settings ) && isset( $settings['autosave_interval'] ) ) {
			$settings['autosave_interval'] = 300;
		}
		return $settings;
	}
}
