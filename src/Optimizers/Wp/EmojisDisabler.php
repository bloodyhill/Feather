<?php
/**
 * Disable WordPress's emoji detection and fallback styles.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Wp;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Strips the wp-emoji detection script + styles + filters that ship in
 * core. Modern browsers render Unicode emoji natively; the script is
 * pure overhead for the rest.
 */
final class EmojisDisabler extends AbstractOptimizer {

	public function id(): string {
		return 'f.wp.emojis';
	}

	public function apply(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', array( $this, 'remove_tinymce_emoji' ) );
	}

	/**
	 * Remove the wpemoji TinyMCE plugin from the loaded list.
	 *
	 * @param mixed $plugins TinyMCE plugin list.
	 * @return array
	 */
	public function remove_tinymce_emoji( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return array();
		}
		return array_diff( $plugins, array( 'wpemoji' ) );
	}
}
