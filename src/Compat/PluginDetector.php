<?php
/**
 * Detect performance-adjacent plugins so the UI can warn about overlap.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only inspector that tells the React UI which other plugins live on
 * the site so we can both surface coexistence info on the Settings page
 * and refuse to enable Feather optimizations that would duplicate them.
 *
 * Detection is based on `is_plugin_active()` — the active plugin slug
 * matches the canonical "vendor-folder/main-file.php" slug published by
 * each plugin. We only report — we never modify another plugin's state.
 */
final class PluginDetector {

	/**
	 * Cache plugins. Active means assets are likely already deferred /
	 * minified / combined; Feather should defer to them.
	 *
	 * @var array<string, string>
	 */
	private const CACHE = array(
		'wp-rocket/wp-rocket.php'                 => 'WP Rocket',
		'litespeed-cache/litespeed-cache.php'     => 'LiteSpeed Cache',
		'sg-cachepress/sg-cachepress.php'         => 'SiteGround Optimizer',
		'w3-total-cache/w3-total-cache.php'       => 'W3 Total Cache',
		'wp-fastest-cache/wpFastestCache.php'     => 'WP Fastest Cache',
		'cache-enabler/cache-enabler.php'         => 'Cache Enabler',
		'autoptimize/autoptimize.php'             => 'Autoptimize',
		'wp-super-cache/wp-cache.php'             => 'WP Super Cache',
		'breeze/breeze.php'                       => 'Breeze',
	);

	/**
	 * Image optimizer plugins.
	 *
	 * @var array<string, string>
	 */
	private const IMAGE_OPTIMIZER = array(
		'imagify/imagify.php'                                => 'Imagify',
		'wp-smushit/wp-smush.php'                            => 'Smush',
		'optimole-wp/optimole-wp.php'                        => 'Optimole',
		'shortpixel-image-optimiser/wp-shortpixel.php'       => 'ShortPixel',
		'ewww-image-optimizer/ewww-image-optimizer.php'      => 'EWWW Image Optimizer',
		'tiny-compress-images/tiny-compress-images.php'      => 'TinyPNG',
	);

	/**
	 * Page builders. We surface these for context — Feather is Elementor-aware
	 * but a site running Bricks alongside Elementor needs to know.
	 *
	 * @var array<string, string>
	 */
	private const BUILDERS = array(
		'elementor/elementor.php'           => 'Elementor',
		'elementor-pro/elementor-pro.php'   => 'Elementor Pro',
		'bricks/bricks.php'                 => 'Bricks',
		'beaver-builder-lite-version/fl-builder.php' => 'Beaver Builder',
		'oxygen/functions.php'              => 'Oxygen',
	);

	/**
	 * All detection results, grouped by family.
	 *
	 * @return array<string, array<int, array{slug: string, name: string}>>
	 */
	public function detect_all(): array {
		return array(
			'cache'           => $this->detect_family( self::CACHE ),
			'image_optimizer' => $this->detect_family( self::IMAGE_OPTIMIZER ),
			'builders'        => $this->detect_family( self::BUILDERS ),
		);
	}

	/**
	 * Detect one family.
	 *
	 * @param array<string, string> $registry slug => display name.
	 * @return array<int, array{slug: string, name: string}>
	 */
	private function detect_family( array $registry ): array {
		$this->ensure_plugin_helpers();

		$found = array();
		foreach ( $registry as $slug => $name ) {
			if ( is_plugin_active( $slug ) ) {
				$found[] = array(
					'slug' => $slug,
					'name' => $name,
				);
			}
		}
		return $found;
	}

	/**
	 * Make sure `is_plugin_active()` is available — it lives in admin only
	 * but PluginDetector may be called from REST contexts that haven't
	 * loaded admin includes.
	 */
	private function ensure_plugin_helpers(): void {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
