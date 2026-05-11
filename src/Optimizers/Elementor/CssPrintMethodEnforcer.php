<?php
/**
 * Force Elementor's CSS print method to "External File".
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor offers two CSS print methods:
 *
 *   - "internal" (default) — every page response inlines its widget CSS as
 *                            a `<style>` block. The HTML can't be cached
 *                            independently of the CSS and the CSS itself
 *                            re-downloads on every request.
 *   - "external"           — Elementor writes one CSS file per post/page
 *                            into uploads/elementor/css and references it
 *                            with a normal `<link>`. The file caches at
 *                            the CDN edge and in the browser.
 *
 * For any production site, "external" is the correct choice. Some hosts
 * and plugin updates revert it; we shortcut the option read so the value
 * Elementor sees is always "external" regardless of what's in the DB.
 *
 * No write to the option — the user's stored setting is preserved and
 * surfaces unchanged after Feather is deactivated.
 */
final class CssPrintMethodEnforcer extends AbstractOptimizer {

	public function id(): string {
		return 'f.elementor.css_print_external';
	}

	public function apply(): void {
		add_filter( 'pre_option_elementor_css_print_method', array( $this, 'force_external' ) );
	}

	/**
	 * Return Elementor's "external" value, matching the string the settings
	 * UI stores and that `\Elementor\Core\Files\CSS\Post::is_meta_dynamic()`
	 * compares against. Returning a non-false value short-circuits the
	 * normal option lookup in WordPress core.
	 *
	 * @return string
	 */
	public function force_external() {
		return 'external';
	}
}
