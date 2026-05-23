<?php
/**
 * Widget→asset mapping with runtime introspection of Elementor's registry.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves "which enqueued asset handles does this Elementor widget pull in"
 * by asking Elementor's own widget registry — `get_style_depends()` and
 * `get_script_depends()` on each registered widget — and falling back to a
 * tight hardcoded overlay for assets Elementor doesn't expose through those
 * methods (chiefly icon libraries loaded at render time by the Icons_Manager).
 *
 * The introspection path automatically covers third-party widgets (Pro,
 * Hello Plus, Rivax, ElementsKit, etc.) without Feather having to maintain
 * an ever-growing per-addon table.
 *
 * Add-ons can still extend or correct the mapping via the
 * `feather/scan_widget_map` filter — those entries are layered on top of
 * the runtime introspection results, never replacing them.
 */
final class WidgetAssetMap {

	/**
	 * Valid asset-handle pattern. WordPress allows letters, digits,
	 * underscores, hyphens. We reject anything else — including the
	 * accidental class-FQN-as-error strings that third-party widgets
	 * sometimes leak from broken get_*_depends() methods.
	 *
	 * @var string
	 */
	private const HANDLE_PATTERN = '/^[a-z0-9_-]+$/';

	/**
	 * Process-wide count of introspection results we rejected. Surfaced
	 * via introspection_failures() so the admin Site Scan view can show
	 * "N widgets failed introspection" without itself running PHP eval.
	 *
	 * @var int
	 */
	private static $failure_count = 0;

	/**
	 * Public accessor for the failure counter.
	 */
	public static function introspection_failures(): int {
		return self::$failure_count;
	}

	/**
	 * Reset the failure counter — useful for tests and for the scanner
	 * to report failures-per-run rather than process-lifetime totals.
	 */
	public static function reset_introspection_failures(): void {
		self::$failure_count = 0;
	}

	/**
	 * Per-request memoization of resolved handles per widget type. The
	 * scanner walks many posts and re-hits the same widget ids; resolving
	 * once per id keeps introspection cost negligible.
	 *
	 * @var array<string, string[]>
	 */
	private $resolved = array();

	/**
	 * The static overlay (defaults plus filter) resolved once per request.
	 *
	 * @var array<string, string[]>|null
	 */
	private $static_map = null;

	/**
	 * The static overlay map. Exposed for tooling that wants to inspect the
	 * hardcoded supplements; the scanner uses `assets_for_widgets()`, which
	 * combines this with Elementor's runtime registry.
	 *
	 * @return array<string, string[]>
	 */
	public function map(): array {
		if ( null === $this->static_map ) {
			$defaults = self::defaults();

			/**
			 * Filter the static widget→asset overlay.
			 *
			 * Entries returned here are added on top of whatever Elementor's
			 * own widget registry declares via `get_style_depends()` and
			 * `get_script_depends()`. Use this filter only for assets that
			 * Elementor doesn't surface itself — typically icon libraries
			 * loaded at render time. Do not list `elementor-frontend` here
			 * (it's a global handle) or any `widget-*` handle (Elementor 4.x
			 * widgets already declare those).
			 *
			 * Shape: `array<string, string[]>` — widget id → handle list.
			 *
			 * @param array<string, string[]> $defaults Default static overlay.
			 */
			$filtered         = apply_filters( 'feather/scan_widget_map', $defaults );
			$this->static_map = is_array( $filtered ) ? $filtered : $defaults;
		}
		return $this->static_map;
	}

	/**
	 * Aggregate the distinct asset handles needed by a list of widget types.
	 *
	 * For each widget id we ask Elementor's widget registry what styles and
	 * scripts it declares, then layer in any static-overlay entries that
	 * target the same widget. The result is order-preserving and de-duped.
	 *
	 * @param string[] $widget_types Widget ids.
	 * @return string[] Distinct asset handles.
	 */
	public function assets_for_widgets( array $widget_types ): array {
		$handles = array();
		foreach ( $widget_types as $type ) {
			if ( ! is_string( $type ) || '' === $type ) {
				continue;
			}
			foreach ( $this->handles_for( $type ) as $handle ) {
				if ( ! in_array( $handle, $handles, true ) ) {
					$handles[] = $handle;
				}
			}
		}
		return $handles;
	}

	/**
	 * Resolve handles for a single widget type, memoized per request.
	 *
	 * @return string[]
	 */
	private function handles_for( string $widget_type ): array {
		if ( isset( $this->resolved[ $widget_type ] ) ) {
			return $this->resolved[ $widget_type ];
		}

		$handles = $this->introspect( $widget_type );

		$static = $this->map();
		if ( isset( $static[ $widget_type ] ) && is_array( $static[ $widget_type ] ) ) {
			foreach ( $static[ $widget_type ] as $handle ) {
				if ( is_string( $handle ) && '' !== $handle && ! in_array( $handle, $handles, true ) ) {
					$handles[] = $handle;
				}
			}
		}

		$this->resolved[ $widget_type ] = $handles;
		return $handles;
	}

	/**
	 * Ask Elementor's registry what assets this widget declares.
	 *
	 * Returns an empty array if Elementor isn't loaded, the widget isn't
	 * registered, or the widget throws while reporting deps — all non-fatal
	 * for the scanner; the caller still applies the static overlay.
	 *
	 * @return string[]
	 */
	private function introspect( string $widget_type ): array {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return array();
		}
		$elementor = \Elementor\Plugin::$instance ?? null;
		if ( null === $elementor || empty( $elementor->widgets_manager ) ) {
			return array();
		}

		try {
			$widget = $elementor->widgets_manager->get_widget_types( $widget_type );
		} catch ( \Throwable $e ) {
			return array();
		}
		if ( ! is_object( $widget ) ) {
			return array();
		}

		$handles = array();
		foreach ( array( 'get_style_depends', 'get_script_depends' ) as $method ) {
			if ( ! method_exists( $widget, $method ) ) {
				continue;
			}
			try {
				$declared = $widget->{$method}();
			} catch ( \Throwable $e ) {
				continue;
			}
			if ( ! is_array( $declared ) ) {
				continue;
			}
			foreach ( $declared as $handle ) {
				if ( ! is_string( $handle ) || '' === $handle ) {
					continue;
				}
				if ( ! preg_match( self::HANDLE_PATTERN, $handle ) ) {
					++self::$failure_count;
					continue;
				}
				if ( ! in_array( $handle, $handles, true ) ) {
					$handles[] = $handle;
				}
			}
		}

		// Elementor's Widget_WordPress class incorrectly declares
		// swiper / e-swiper as deps for every wp-widget-* type. Strip them
		// for any wp-widget-* widget; if a wp-widget genuinely needs swiper
		// later, the static overlay below adds it back explicitly.
		if ( 0 === strpos( $widget_type, 'wp-widget-' ) ) {
			$handles = array_values( array_filter(
				$handles,
				static fn( $h ) => 'swiper' !== $h && 'e-swiper' !== $h
			) );
		}

		return $handles;
	}

	/**
	 * Static overlay applied on top of Elementor's runtime introspection.
	 *
	 * Limited to assets Elementor itself doesn't expose via the
	 * `get_*_depends()` API. The icon libraries below are picked at render
	 * time by `Elementor\Icons_Manager` based on the user's chosen icon —
	 * `get_style_depends()` doesn't see them. Everything else (per-widget
	 * CSS handles like `widget-tabs`, `widget-heading`, conditional script
	 * deps like `swiper`/`jquery-numerator`) is sourced from introspection.
	 *
	 * @return array<string, string[]>
	 */
	private static function defaults(): array {
		// `elementor-icons` is the actual registered stylesheet handle for
		// the eicons font in Elementor 4.x (see `Frontend::register_styles`).
		// "eicons" is only the CSS font-family name, not an enqueue handle.
		return array(
			'icon-box'             => array( 'elementor-icons', 'elementor-icons-fa-solid' ),
			'icon-list'            => array( 'elementor-icons' ),
			'social-icons'         => array( 'elementor-icons', 'elementor-icons-fa-brands' ),
			'icon'                 => array( 'elementor-icons' ),
			// Atomic elements: not registered in widgets_manager (they're element
			// types), so introspection returns nothing for them. Their handler
			// scripts pull in the rest of the v4 chain via WP deps.
			'e-tabs'               => array( 'elementor-tabs-handler' ),
			// Atomic Self-Hosted Video — Elementor 4.0.x. Loads its own handler
			// to bind play/pause/poster controls; the same handler also covers
			// the e-video element type when present.
			'e-self-hosted-video'  => array( 'elementor-video-handler' ),
			// Atomic YouTube — Elementor 4.0.x.
			'e-youtube'            => array( 'elementor-youtube-handler' ),
		);
	}
}
