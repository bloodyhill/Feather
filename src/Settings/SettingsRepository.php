<?php
/**
 * Settings repository.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes Feather's single autoloaded option.
 *
 * Schema:
 *   array(
 *       'schema_version' => int,
 *       'features'       => array<string, bool>, // feature id => enabled
 *       'preset'         => string, // 'conservative' | 'balanced' | 'aggressive' | 'custom'
 *       'theme'          => string, // 'system' | 'light' | 'dark'
 *       'usage_opt_in'   => bool,
 *       'optimizers_paused' => bool, // master kill switch — when true, no optimizer applies
 *   )
 *
 * Bulky data (scan results, metrics history) lives in custom tables, not here.
 */
final class SettingsRepository {

	public const OPTION_KEY     = 'feather_settings';
	public const SCHEMA_VERSION = 2;

	public const PRESET_CONSERVATIVE = 'conservative';
	public const PRESET_BALANCED     = 'balanced';
	public const PRESET_AGGRESSIVE   = 'aggressive';
	public const PRESET_CUSTOM       = 'custom';

	public const THEME_SYSTEM = 'system';
	public const THEME_LIGHT  = 'light';
	public const THEME_DARK   = 'dark';

	// Heartbeat per-context defaults (seconds for editor/admin; bool for frontend).
	public const HEARTBEAT_EDITOR_DEFAULT   = 60;
	public const HEARTBEAT_ADMIN_DEFAULT    = 60;
	public const HEARTBEAT_EDITOR_MIN       = 15;
	public const HEARTBEAT_EDITOR_MAX       = 600;
	public const HEARTBEAT_ADMIN_MIN        = 15;
	public const HEARTBEAT_ADMIN_MAX        = 600;

	/**
	 * In-memory cache of the merged settings array. Null until first read.
	 *
	 * @var array<string, mixed>|null
	 */
	private $cache = null;

	/**
	 * All settings, with defaults filled in for missing keys.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			$this->cache = wp_parse_args( $stored, $this->defaults() );
			if ( ! isset( $this->cache['features'] ) || ! is_array( $this->cache['features'] ) ) {
				$this->cache['features'] = array();
			}
		}

		return $this->cache;
	}

	/**
	 * Whether a feature toggle is enabled.
	 *
	 * @param string $feature_id Feature id.
	 */
	public function is_enabled( string $feature_id ): bool {
		$all = $this->all();
		return ! empty( $all['features'][ $feature_id ] );
	}

	/**
	 * Set a single feature toggle.
	 *
	 * @param string $feature_id Feature id.
	 * @param bool   $enabled    Whether to enable.
	 */
	public function set_enabled( string $feature_id, bool $enabled ): void {
		$all                              = $this->all();
		$all['features'][ $feature_id ]   = $enabled;
		$this->save( $all );
	}

	/**
	 * Master kill switch — when true, Plugin::apply_optimizers() bails before
	 * registering any optimizer hooks. Users toggle this from the Dashboard's
	 * "Pause all optimizations" card while editing in Elementor.
	 */
	public function is_optimizers_paused(): bool {
		$all = $this->all();
		return ! empty( $all['optimizers_paused'] );
	}

	/**
	 * Set the master pause flag. Writes the merged settings array back to
	 * the option store and refreshes the in-memory cache.
	 *
	 * @param bool $paused Whether optimizers should be paused.
	 */
	public function set_optimizers_paused( bool $paused ): void {
		$all                      = $this->all();
		$all['optimizers_paused'] = $paused;
		$this->save( $all );
	}

	/**
	 * Persist a settings array.
	 *
	 * @param array<string, mixed> $settings Settings to save.
	 */
	public function save( array $settings ): void {
		$settings['schema_version'] = self::SCHEMA_VERSION;
		update_option( self::OPTION_KEY, $settings, true );
		$this->cache = $settings;

		// Belt-and-braces cache invalidation. WordPress's update_option() is
		// supposed to refresh the options cache, but some Redis, Memcached,
		// and LiteSpeed object caches don't honor that invalidation across
		// follow-up requests. Forcing a re-read on the next get_option()
		// guarantees the canonical DB value is what subsequent requests see.
		wp_cache_delete( self::OPTION_KEY, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	/**
	 * Read a single top-level key.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Default if key is missing.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Reset to defaults (does not touch scan/metrics tables).
	 */
	public function reset(): void {
		$this->save( $this->defaults() );
	}

	/**
	 * Read advanced settings for the consolidated heartbeat feature.
	 *
	 * Shape: [
	 *   'frontend_disabled' => bool,  // remove heartbeat from public frontend
	 *   'editor_interval'   => int,   // seconds, clamped to [15, 600]
	 *   'admin_interval'    => int,   // seconds, clamped to [15, 600]
	 * ]
	 *
	 * @return array{frontend_disabled: bool, editor_interval: int, admin_interval: int}
	 */
	public function heartbeat_advanced(): array {
		$all     = $this->all();
		$raw     = isset( $all['advanced']['heartbeat'] ) && is_array( $all['advanced']['heartbeat'] )
			? $all['advanced']['heartbeat']
			: array();
		$editor  = isset( $raw['editor_interval'] ) ? (int) $raw['editor_interval'] : self::HEARTBEAT_EDITOR_DEFAULT;
		$admin   = isset( $raw['admin_interval'] ) ? (int) $raw['admin_interval'] : self::HEARTBEAT_ADMIN_DEFAULT;
		$front   = array_key_exists( 'frontend_disabled', $raw ) ? (bool) $raw['frontend_disabled'] : true;

		return array(
			'frontend_disabled' => $front,
			'editor_interval'   => max( self::HEARTBEAT_EDITOR_MIN, min( self::HEARTBEAT_EDITOR_MAX, $editor ) ),
			'admin_interval'    => max( self::HEARTBEAT_ADMIN_MIN, min( self::HEARTBEAT_ADMIN_MAX, $admin ) ),
		);
	}

	/**
	 * Write advanced settings for the consolidated heartbeat feature.
	 *
	 * Each key is optional; only provided keys overwrite.
	 *
	 * @param array<string, mixed> $advanced Partial advanced settings.
	 */
	public function set_heartbeat_advanced( array $advanced ): void {
		$current = $this->heartbeat_advanced();
		if ( array_key_exists( 'frontend_disabled', $advanced ) ) {
			$current['frontend_disabled'] = (bool) $advanced['frontend_disabled'];
		}
		if ( array_key_exists( 'editor_interval', $advanced ) ) {
			$current['editor_interval'] = max(
				self::HEARTBEAT_EDITOR_MIN,
				min( self::HEARTBEAT_EDITOR_MAX, (int) $advanced['editor_interval'] )
			);
		}
		if ( array_key_exists( 'admin_interval', $advanced ) ) {
			$current['admin_interval'] = max(
				self::HEARTBEAT_ADMIN_MIN,
				min( self::HEARTBEAT_ADMIN_MAX, (int) $advanced['admin_interval'] )
			);
		}

		$all                              = $this->all();
		if ( ! isset( $all['advanced'] ) || ! is_array( $all['advanced'] ) ) {
			$all['advanced'] = array();
		}
		$all['advanced']['heartbeat'] = $current;
		$this->save( $all );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'schema_version'    => self::SCHEMA_VERSION,
			'features'          => array(),
			'preset'            => self::PRESET_CONSERVATIVE,
			'theme'             => self::THEME_LIGHT,
			'usage_opt_in'      => false,
			'optimizers_paused' => false,
			'advanced'          => array(
				'heartbeat' => array(
					'frontend_disabled' => true,
					'editor_interval'   => self::HEARTBEAT_EDITOR_DEFAULT,
					'admin_interval'    => self::HEARTBEAT_ADMIN_DEFAULT,
				),
			),
		);
	}
}
