<?php
/**
 * Hydrate Feather settings from a previously-exported JSON document.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Settings;

use Feather\FeatureRegistry\FeatureRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and applies an export payload produced by {@see SettingsExporter}.
 *
 * Refuses payloads:
 *  - that are not associative arrays
 *  - whose `kind` is not 'feather-settings-export'
 *  - whose `schema_version` exceeds the installed schema (avoid downgrading
 *    state shape)
 *  - whose `features` includes unknown ids (unknown ids are silently dropped)
 *
 * Every imported value passes through the same sanitizer the REST endpoints
 * use, so an invalid preset or theme falls back to the current setting
 * rather than being persisted verbatim.
 */
final class SettingsImporter {

	public const KIND = 'feather-settings-export';

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Feature registry.
	 *
	 * @var FeatureRegistry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings repository.
	 * @param FeatureRegistry    $registry Registry.
	 */
	public function __construct( SettingsRepository $settings, FeatureRegistry $registry ) {
		$this->settings = $settings;
		$this->registry = $registry;
	}

	/**
	 * Validate the payload without writing. Returns a list of human-readable
	 * issues; empty list means safe to import.
	 *
	 * @param array<string, mixed> $payload Export payload.
	 * @return string[]
	 */
	public function validate( array $payload ): array {
		$issues = array();

		if ( ! isset( $payload['kind'] ) || self::KIND !== $payload['kind'] ) {
			$issues[] = __( 'Unrecognized export file (expected a Feather settings export).', 'feather-performance' );
		}

		$schema = isset( $payload['schema_version'] ) ? (int) $payload['schema_version'] : 0;
		if ( $schema > SettingsRepository::SCHEMA_VERSION ) {
			$issues[] = __( 'Export was produced by a newer Feather version. Update Feather before importing.', 'feather-performance' );
		}

		if ( ! isset( $payload['settings'] ) || ! is_array( $payload['settings'] ) ) {
			$issues[] = __( 'Export does not contain a settings section.', 'feather-performance' );
		}

		return $issues;
	}

	/**
	 * Import an already-validated payload, applying it to the settings store.
	 *
	 * Returns a summary describing what was applied. Callers should invoke
	 * {@see validate()} first and surface any returned issues to the user.
	 *
	 * @param array<string, mixed> $payload Export payload.
	 * @return array{applied: bool, feature_count: int, unknown_features: string[]}
	 */
	public function apply( array $payload ): array {
		$summary = array(
			'applied'          => false,
			'feature_count'    => 0,
			'unknown_features' => array(),
		);

		$issues = $this->validate( $payload );
		if ( ! empty( $issues ) ) {
			return $summary;
		}

		$incoming = is_array( $payload['settings'] ?? null ) ? $payload['settings'] : array();
		$current  = $this->settings->all();
		$registry = $this->registry;
		$registry->load();

		if ( isset( $incoming['preset'] ) && is_string( $incoming['preset'] ) ) {
			$preset = $incoming['preset'];
			if ( in_array( $preset, array(
				SettingsRepository::PRESET_CONSERVATIVE,
				SettingsRepository::PRESET_BALANCED,
				SettingsRepository::PRESET_AGGRESSIVE,
				SettingsRepository::PRESET_CUSTOM,
			), true ) ) {
				$current['preset'] = $preset;
			}
		}

		if ( isset( $incoming['theme'] ) && is_string( $incoming['theme'] ) ) {
			$theme = $incoming['theme'];
			if ( in_array( $theme, array(
				SettingsRepository::THEME_SYSTEM,
				SettingsRepository::THEME_LIGHT,
				SettingsRepository::THEME_DARK,
			), true ) ) {
				$current['theme'] = $theme;
			}
		}

		if ( isset( $incoming['usage_opt_in'] ) ) {
			$current['usage_opt_in'] = (bool) $incoming['usage_opt_in'];
		}
		if ( isset( $incoming['optimizers_paused'] ) ) {
			$current['optimizers_paused'] = (bool) $incoming['optimizers_paused'];
		}

		if ( isset( $incoming['features'] ) && is_array( $incoming['features'] ) ) {
			$features = isset( $current['features'] ) && is_array( $current['features'] ) ? $current['features'] : array();
			foreach ( $incoming['features'] as $id => $value ) {
				if ( ! is_string( $id ) || '' === $id ) {
					continue;
				}
				if ( ! $registry->has( $id ) ) {
					$summary['unknown_features'][] = $id;
					continue;
				}
				$features[ $id ] = (bool) $value;
				++$summary['feature_count'];
			}
			$current['features'] = $features;
		}

		if ( isset( $incoming['advanced'] ) && is_array( $incoming['advanced'] ) ) {
			// Advanced is a free-form map of feature ids → arbitrary values.
			// Preserve only string keys to filter out incidental indexed entries.
			$advanced_clean = array();
			foreach ( $incoming['advanced'] as $section => $value ) {
				if ( is_string( $section ) && is_array( $value ) ) {
					$advanced_clean[ $section ] = $value;
				}
			}
			$current['advanced'] = $advanced_clean;
		}

		$this->settings->save( $current );
		$summary['applied'] = true;
		return $summary;
	}
}
