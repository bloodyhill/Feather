<?php
/**
 * Strip Elementor's redundant DOM artifacts.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the `?ver=` query string from Elementor's static asset URLs (better
 * caching) and detaches the redundant Google-Fonts preconnect tag Elementor
 * emits in `<head>` (its href is built from the same URL the actual @import
 * already provides — preconnecting to it twice is wasted bytes).
 */
final class DomBloatRemover extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.dom_bloat';
	}

	public function apply(): void {
		add_filter( 'style_loader_src', array( $this, 'strip_version_query' ), 9999 );
		add_filter( 'script_loader_src', array( $this, 'strip_version_query' ), 9999 );
		add_action( 'wp_head', array( $this, 'detach_preconnect_tag' ), 0 );
	}

	/**
	 * Strip `?ver=` from URLs that look like Elementor's own assets.
	 *
	 * @param string $src Source URL.
	 * @return string
	 */
	public function strip_version_query( $src ) {
		if ( ! is_string( $src ) || false === strpos( $src, 'elementor' ) ) {
			return $src;
		}
		return remove_query_arg( 'ver', $src );
	}

	/**
	 * Detach Elementor's hard-coded Google Fonts preconnect tag emitter.
	 */
	public function detach_preconnect_tag(): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		$elementor = \Elementor\Plugin::$instance ?? null;
		if ( null === $elementor || empty( $elementor->frontend ) || ! is_object( $elementor->frontend ) ) {
			return;
		}
		if ( method_exists( $elementor->frontend, 'print_google_fonts_preconnect_tag' ) ) {
			remove_action( 'wp_head', array( $elementor->frontend, 'print_google_fonts_preconnect_tag' ) );
		}
	}
}
