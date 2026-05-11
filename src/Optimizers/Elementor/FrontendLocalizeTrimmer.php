<?php
/**
 * Trim editor-only keys from Elementor's localized frontend config.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor inlines an `elementorFrontendConfig` JSON blob into every
 * frontend page that loads its JS bundle. On heavily-configured sites it
 * runs to 5-15 KB of inline JSON — and most of the payload is editor UI
 * strings the frontend never reads.
 *
 * The `elementor/frontend/localize_settings` filter is the documented
 * extension point Elementor itself uses; we strip the editor-only keys
 * before the JSON is encoded and printed. Late priority (99) lets other
 * plugins add or override their own keys first.
 *
 * Keys removed on every frontend render:
 *   - `i18n`            — translated UI strings used in the editor panel
 *   - `loaderUrl`       — points at the editor's asset loader
 *   - `beta`            — beta-features flag, never read by the frontend JS
 *
 * `environmentMode` is preserved when its value is `edit` (so the editor
 * preview iframe still detects the right context) and dropped otherwise.
 */
final class FrontendLocalizeTrimmer extends AbstractOptimizer {

	/**
	 * Keys guaranteed unused on the frontend.
	 *
	 * @var string[]
	 */
	private const EDITOR_ONLY_KEYS = array( 'i18n', 'loaderUrl', 'beta' );

	public function id(): string {
		return 'f.elementor.localize_trim';
	}

	public function apply(): void {
		add_filter( 'elementor/frontend/localize_settings', array( $this, 'trim_settings' ), 99 );
	}

	/**
	 * Drop editor-only keys from the frontend settings array.
	 *
	 * @param mixed $settings Filtered settings — should be an array.
	 * @return mixed
	 */
	public function trim_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		foreach ( self::EDITOR_ONLY_KEYS as $key ) {
			unset( $settings[ $key ] );
		}

		if ( isset( $settings['environmentMode'] ) && 'edit' !== $settings['environmentMode'] ) {
			unset( $settings['environmentMode'] );
		}

		return $settings;
	}
}
