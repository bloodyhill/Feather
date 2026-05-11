<?php
/**
 * Tracks whether the user has completed (or dismissed) the welcome banner.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Onboarding;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal state machine for the Dashboard's welcome banner.
 *
 * Three states:
 *   - "pending": user has never dismissed → banner shown.
 *   - "scanned": user clicked "Run scan now" → banner shown until scan completes.
 *   - "completed": user dismissed or finished a scan → banner permanently hidden.
 */
final class OnboardingState {

	public const OPTION_KEY = 'feather_onboarding_state';

	public const STATE_PENDING   = 'pending';
	public const STATE_SCANNED   = 'scanned';
	public const STATE_COMPLETED = 'completed';

	/**
	 * Current state.
	 */
	public function state(): string {
		$value = get_option( self::OPTION_KEY, self::STATE_PENDING );
		if ( ! is_string( $value ) ) {
			return self::STATE_PENDING;
		}
		if ( ! in_array( $value, array( self::STATE_PENDING, self::STATE_SCANNED, self::STATE_COMPLETED ), true ) ) {
			return self::STATE_PENDING;
		}
		return $value;
	}

	/**
	 * Persist a new state.
	 *
	 * @param string $state State constant.
	 */
	public function set_state( string $state ): void {
		if ( ! in_array( $state, array( self::STATE_PENDING, self::STATE_SCANNED, self::STATE_COMPLETED ), true ) ) {
			return;
		}
		update_option( self::OPTION_KEY, $state, true );
	}

	/**
	 * Whether the dashboard banner should be shown.
	 */
	public function should_show_banner(): bool {
		return self::STATE_COMPLETED !== $this->state();
	}

	/**
	 * REST-friendly serialization.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'state'        => $this->state(),
			'show_banner'  => $this->should_show_banner(),
		);
	}
}
