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
 *   )
 *
 * Bulky data (scan results, metrics history) lives in custom tables, not here.
 */
final class SettingsRepository {

	public const OPTION_KEY     = 'feather_settings';
	public const SCHEMA_VERSION = 1;

	public const PRESET_CONSERVATIVE = 'conservative';
	public const PRESET_BALANCED     = 'balanced';
	public const PRESET_AGGRESSIVE   = 'aggressive';
	public const PRESET_CUSTOM       = 'custom';

	public const THEME_SYSTEM = 'system';
	public const THEME_LIGHT  = 'light';
	public const THEME_DARK   = 'dark';

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
	 * Persist a settings array.
	 *
	 * @param array<string, mixed> $settings Settings to save.
	 */
	public function save( array $settings ): void {
		$settings['schema_version'] = self::SCHEMA_VERSION;
		update_option( self::OPTION_KEY, $settings, true );
		$this->cache = $settings;
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
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'features'       => array(),
			'preset'         => self::PRESET_CONSERVATIVE,
			'theme'          => self::THEME_SYSTEM,
			'usage_opt_in'   => false,
		);
	}
}
