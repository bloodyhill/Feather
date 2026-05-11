<?php
/**
 * Walks a post's saved Elementor JSON tree to extract widget usage.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Reads `_elementor_data` post meta, decodes the JSON, and recursively
 * harvests every `widgetType` along with a small set of settings flags
 * that drive scan-gated feature recommendations.
 *
 * The parser is intentionally tolerant: malformed JSON, missing meta,
 * unexpected node shapes — none of these throw. The caller gets an
 * empty ScanResult and the scan continues.
 */
final class ElementorJsonParser {

	/**
	 * Widget asset map for resolving asset handles.
	 *
	 * @var WidgetAssetMap
	 */
	private $asset_map;

	/**
	 * Constructor.
	 *
	 * @param WidgetAssetMap $asset_map Widget→asset map.
	 */
	public function __construct( WidgetAssetMap $asset_map ) {
		$this->asset_map = $asset_map;
	}

	/**
	 * Atomic widget keys (Elementor 4.0+). Detected via the existing
	 * widgetType branch in walk() — listed here for the flag check.
	 *
	 * @var string[]
	 */
	private const ATOMIC_WIDGET_TYPES = array(
		'e-button',
		'e-component',
		'e-divider',
		'e-heading',
		'e-image',
		'e-paragraph',
		'e-self-hosted-video',
		'e-svg',
		'e-youtube',
	);

	/**
	 * Atomic element types. These appear with `elType` directly and no
	 * `widgetType`. Most are pure containers (no asset deps); e-tabs
	 * additionally needs `elementor-tabs-handler`, so it gets recorded
	 * as a widget type for asset-map resolution.
	 *
	 * @var string[]
	 */
	private const ATOMIC_ELEMENT_TYPES = array(
		'e-div-block',
		'e-flexbox',
		'e-tabs',
		'e-tabs-menu',
		'e-tab',
		'e-tabs-content-area',
		'e-tab-content',
	);

	/**
	 * Atomic element types that themselves pull a unique asset (so the
	 * scanner must record them as widget types for WidgetAssetMap lookup).
	 *
	 * @var string[]
	 */
	private const ATOMIC_ELEMENTS_WITH_ASSETS = array(
		'e-tabs',
	);

	/**
	 * Parse a single post's Elementor data into a ScanResult.
	 *
	 * @param int $post_id Post id.
	 */
	public function parse( int $post_id ): ScanResult {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return new ScanResult( $post_id, array(), array(), $this->empty_flags() );
		}

		$tree = json_decode( $raw, true );
		if ( ! is_array( $tree ) ) {
			return new ScanResult( $post_id, array(), array(), $this->empty_flags() );
		}

		$widget_types = array();
		$flags        = $this->empty_flags();
		$this->walk( $tree, $widget_types, $flags );

		$assets = $this->asset_map->assets_for_widgets( $widget_types );

		return new ScanResult( $post_id, $widget_types, $assets, $flags );
	}

	/**
	 * Empty flag set.
	 *
	 * @return array<string, bool>
	 */
	private function empty_flags(): array {
		return array(
			'uses_google_fonts'   => false,
			'uses_eicons'         => false,
			'uses_fa_icons'       => false,
			'uses_lottie'         => false,
			'has_atomic_widgets'  => false,
		);
	}

	/**
	 * Recursive walker. Mutates $types and $flags in place to avoid
	 * rebuilding large arrays as we descend.
	 *
	 * @param array<int|string, mixed> $nodes  Current node array.
	 * @param string[]                 $types  Accumulated widget types.
	 * @param array<string, bool>      $flags  Accumulated settings flags.
	 */
	private function walk( array $nodes, array &$types, array &$flags ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			// Path A: traditional widget — elType='widget', widgetType=<name>.
			// Atomic widgets in 4.0.x also use this shape (widgetType='e-heading').
			if ( ! empty( $node['widgetType'] ) && is_string( $node['widgetType'] ) ) {
				$widget_type = $node['widgetType'];
				if ( ! in_array( $widget_type, $types, true ) ) {
					$types[] = $widget_type;
				}
				if ( in_array( $widget_type, self::ATOMIC_WIDGET_TYPES, true ) ) {
					$flags['has_atomic_widgets'] = true;
				}
				if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
					$this->inspect_settings( $widget_type, $node['settings'], $flags );
				}
			}

			// Path B: atomic element — elType=<name>, no widgetType.
			$el_type = isset( $node['elType'] ) && is_string( $node['elType'] ) ? $node['elType'] : '';
			if ( '' !== $el_type && in_array( $el_type, self::ATOMIC_ELEMENT_TYPES, true ) ) {
				$flags['has_atomic_widgets'] = true;
				// Record asset-bearing atomic elements (currently just e-tabs) as
				// "widget types" so WidgetAssetMap can resolve their handler scripts.
				if ( in_array( $el_type, self::ATOMIC_ELEMENTS_WITH_ASSETS, true )
					&& ! in_array( $el_type, $types, true )
				) {
					$types[] = $el_type;
				}
			}

			if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->walk( $node['elements'], $types, $flags );
			}
		}
	}

	/**
	 * Look at a widget's settings dict for asset-implication signals.
	 *
	 * @param string                $widget_type Widget id.
	 * @param array<string, mixed>  $settings    Settings array.
	 * @param array<string, bool>   $flags       Flags reference.
	 */
	private function inspect_settings( string $widget_type, array $settings, array &$flags ): void {
		// Lottie widgets imply Lottie runtime.
		if ( 'lottie' === $widget_type ) {
			$flags['uses_lottie'] = true;
		}

		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			// Typography group settings include "font_family". Elementor stores Google
			// Font names verbatim (e.g. "Roboto"); a non-empty value with no system-font
			// keyword counts as Google Fonts usage.
			if ( false !== strpos( $key, 'font_family' ) && is_string( $value ) && '' !== trim( $value ) ) {
				$flags['uses_google_fonts'] = true;
			}

			// Icon fields are arrays like [ 'value' => 'fas fa-cog', 'library' => 'fa-solid' ]
			// or strings like "eicon-cog" depending on Elementor version.
			if ( false !== strpos( $key, 'icon' ) || 'selected_icon' === $key ) {
				$this->inspect_icon_value( $value, $flags );
			}
		}
	}

	/**
	 * Inspect a single icon-shaped settings value.
	 *
	 * @param mixed                $value Settings value.
	 * @param array<string, bool>  $flags Flags reference.
	 */
	private function inspect_icon_value( $value, array &$flags ): void {
		if ( is_string( $value ) ) {
			if ( false !== strpos( $value, 'eicon' ) ) {
				$flags['uses_eicons'] = true;
			}
			if ( false !== strpos( $value, 'fa-' ) ) {
				$flags['uses_fa_icons'] = true;
			}
			return;
		}
		if ( is_array( $value ) ) {
			$inner = '';
			if ( isset( $value['value'] ) && is_string( $value['value'] ) ) {
				$inner = $value['value'];
			}
			$library = isset( $value['library'] ) && is_string( $value['library'] ) ? $value['library'] : '';

			if ( 'eicons' === $library || false !== strpos( $inner, 'eicon' ) ) {
				$flags['uses_eicons'] = true;
			}
			if ( false !== strpos( $library, 'fa-' ) || false !== strpos( $inner, 'fa-' ) ) {
				$flags['uses_fa_icons'] = true;
			}
		}
	}
}
