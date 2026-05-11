<?php
/**
 * Capability mapper for Feather.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes the single capability check Feather uses for admin + REST.
 *
 * Maps the virtual `manage_feather` capability onto `manage_options`
 * via the `user_has_cap` filter, so future role-based features can
 * override the mapping per role without touching every call site.
 */
final class Capability {

	public const MANAGE = 'manage_feather';

	public function register(): void {
		add_filter( 'user_has_cap', array( $this, 'grant_to_admins' ), 10, 4 );
	}

	/**
	 * Grant `manage_feather` to anyone who already has `manage_options`.
	 *
	 * @param array<string, bool> $allcaps All caps the user already has.
	 * @param array<int, string>  $caps    Required caps for the check.
	 * @param array<int, mixed>   $args    Original `current_user_can()` args.
	 * @param mixed               $user    The WP_User instance.
	 * @return array<string, bool>
	 */
	public function grant_to_admins( $allcaps, $caps, $args, $user ): array {
		unset( $caps, $args, $user );
		if ( ! is_array( $allcaps ) ) {
			return array();
		}
		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ self::MANAGE ] = true;
		}
		return $allcaps;
	}

	/**
	 * Convenience wrapper around `current_user_can( 'manage_feather' )`.
	 */
	public static function user_can(): bool {
		return current_user_can( self::MANAGE );
	}
}
