<?php
/**
 * Serialize Feather settings to a portable JSON document.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Produces a JSON-ready array representing a Feather settings snapshot that
 * can be re-imported on another site or after a reset.
 *
 * Excludes site-specific state — install date, scan/metrics tables, schema
 * version — so the export reflects only user-tunable configuration.
 */
final class SettingsExporter {

	public const EXPORT_VERSION = 1;

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings repository.
	 */
	public function __construct( SettingsRepository $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Build the export array. Pure data — caller wraps in JSON encoding +
	 * Content-Disposition for the download flow.
	 *
	 * @return array<string, mixed>
	 */
	public function export(): array {
		$all = $this->settings->all();

		$features = isset( $all['features'] ) && is_array( $all['features'] ) ? $all['features'] : array();
		$advanced = isset( $all['advanced'] ) && is_array( $all['advanced'] ) ? $all['advanced'] : array();

		return array(
			'kind'            => 'feather-settings-export',
			'export_version'  => self::EXPORT_VERSION,
			'feather_version' => defined( 'FEATHER_VERSION' ) ? FEATHER_VERSION : '',
			'schema_version'  => SettingsRepository::SCHEMA_VERSION,
			'generated_at'    => gmdate( 'c' ),
			'settings'        => array(
				'preset'            => isset( $all['preset'] ) ? (string) $all['preset'] : SettingsRepository::PRESET_CONSERVATIVE,
				'theme'             => isset( $all['theme'] ) ? (string) $all['theme'] : SettingsRepository::THEME_LIGHT,
				'usage_opt_in'      => ! empty( $all['usage_opt_in'] ),
				'optimizers_paused' => ! empty( $all['optimizers_paused'] ),
				'features'          => $this->clean_features( $features ),
				'advanced'          => $advanced,
			),
		);
	}

	/**
	 * Filter the features map to bool values keyed by string id.
	 *
	 * @param array<mixed, mixed> $features Raw features map.
	 * @return array<string, bool>
	 */
	private function clean_features( array $features ): array {
		$clean = array();
		foreach ( $features as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}
			$clean[ $key ] = (bool) $value;
		}
		return $clean;
	}
}
