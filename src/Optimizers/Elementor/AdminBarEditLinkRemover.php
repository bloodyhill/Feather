<?php
/**
 * Remove Elementor's "Edit with Elementor" node from the WordPress admin bar.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Drops Elementor's "Edit with Elementor" admin bar item.
 *
 * Why `wp_before_admin_bar_render` instead of `admin_bar_menu`:
 * Elementor 4.0.x registers its `elementor_edit_page` node late in the
 * `admin_bar_menu` lifecycle (Site Editor children get appended via filter,
 * and at least one of them ships with a `priority` higher than the usual
 * 999 ceiling used by plugin removers). Hooking `admin_bar_menu` at
 * priority 999 can fire *before* Elementor's add, so `remove_node()` finds
 * nothing to remove. `wp_before_admin_bar_render` fires after every
 * `admin_bar_menu` callback has run — at that point the node is guaranteed
 * to be present if Elementor wants it there.
 *
 * Belt-and-braces CSS: a tiny inline `display:none` rule on
 * `#wp-admin-bar-elementor_edit_page` guarantees the item is hidden even
 * if some third party re-adds the node after our removal (or if an
 * unforeseen Elementor change breaks the node-removal call).
 */
final class AdminBarEditLinkRemover extends AbstractOptimizer {

	private const NODE_ID = 'elementor_edit_page';

	public function id(): string {
		return 'f.elementor.admin_bar_edit_link';
	}

	public function apply(): void {
		// Primary: remove the node at the canonical late-in-lifecycle hook.
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_node' ), 99 );
		// Belt-and-braces CSS in case the removal ever misses.
		add_action( 'wp_head',    array( $this, 'inline_hide_css' ), 100 );
		add_action( 'admin_head', array( $this, 'inline_hide_css' ), 100 );
	}

	/**
	 * Strip the Elementor "Edit with Elementor" admin bar node and (via
	 * cascade) its Site Editor children.
	 */
	public function remove_node(): void {
		if ( $this->is_disabled_for_current_request() ) {
			return;
		}
		global $wp_admin_bar;
		if ( ! is_object( $wp_admin_bar ) || ! method_exists( $wp_admin_bar, 'remove_node' ) ) {
			return;
		}
		$wp_admin_bar->remove_node( self::NODE_ID );
	}

	/**
	 * Emit an inline style that hides the admin bar item. Trivial bytes,
	 * scoped to a single id selector — never affects anything else.
	 *
	 * Note: we deliberately do NOT register/enqueue this via wp_add_inline_style
	 * since the admin bar styles aren't always part of our enqueue graph and
	 * we want the rule present even on minimal admin screens.
	 */
	public function inline_hide_css(): void {
		if ( $this->is_disabled_for_current_request() ) {
			return;
		}
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		echo '<style id="feather-hide-elementor-edit-link">#wp-admin-bar-elementor_edit_page{display:none!important}</style>';
	}
}
