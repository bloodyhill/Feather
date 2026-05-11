<?php
/**
 * Per-post scan result.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable record of one post's parsed Elementor data.
 */
final class ScanResult {

	/**
	 * Post id.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Distinct widget types found on the post (e.g. "icon-box", "image-carousel").
	 *
	 * @var string[]
	 */
	private $widget_types;

	/**
	 * Asset handles likely required by those widgets, looked up via WidgetAssetMap.
	 *
	 * @var string[]
	 */
	private $asset_handles;

	/**
	 * Boolean flags about settings encountered during the parse.
	 *
	 * Keys: uses_google_fonts, uses_eicons, uses_lottie, uses_fa_icons.
	 *
	 * @var array<string, bool>
	 */
	private $settings_flags;

	/**
	 * Constructor.
	 *
	 * @param int                  $post_id        Post id.
	 * @param string[]             $widget_types   Distinct widget types.
	 * @param string[]             $asset_handles  Asset handles.
	 * @param array<string, bool>  $settings_flags Flags.
	 */
	public function __construct(
		int $post_id,
		array $widget_types,
		array $asset_handles,
		array $settings_flags
	) {
		$this->post_id        = $post_id;
		$this->widget_types   = array_values( array_unique( $widget_types ) );
		$this->asset_handles  = array_values( array_unique( $asset_handles ) );
		$this->settings_flags = $settings_flags;
	}

	public function post_id(): int {
		return $this->post_id;
	}

	/**
	 * @return string[]
	 */
	public function widget_types(): array {
		return $this->widget_types;
	}

	/**
	 * @return string[]
	 */
	public function asset_handles(): array {
		return $this->asset_handles;
	}

	/**
	 * @return array<string, bool>
	 */
	public function settings_flags(): array {
		return $this->settings_flags;
	}
}
