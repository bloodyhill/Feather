<?php
/**
 * Disable WordPress's `wp-embed.min.js` and oEmbed discovery.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Wp;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the embed JS and the discovery `<link>` tags from `<head>`.
 * Sites that don't expect to be embedded elsewhere shed a small but
 * universal cost.
 */
final class EmbedsDisabler extends AbstractOptimizer {

	public function id(): string {
		return 'f.wp.embeds';
	}

	public function apply(): void {
		add_action( 'init', array( $this, 'detach_embeds' ), 9999 );
	}

	/**
	 * Detach all embed-related handlers.
	 */
	public function detach_embeds(): void {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		wp_deregister_script( 'wp-embed' );
	}
}
