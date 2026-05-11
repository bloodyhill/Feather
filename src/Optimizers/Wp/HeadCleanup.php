<?php
/**
 * Remove rarely-needed `<head>` artifacts emitted by WordPress core.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Wp;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Strips RSD, Windows Live Writer manifest, shortlink, and the
 * `<meta generator>` tag — all of which are surface-area for either
 * crawlers or attackers and serve no purpose on a modern WP site.
 */
final class HeadCleanup extends AbstractOptimizer {

	public function id(): string {
		return 'f.wp.head_cleanup';
	}

	public function apply(): void {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'wp_generator' );
	}
}
