<?php
/**
 * GET /settings, POST /settings
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
use Feather\FeatureRegistry\FeatureRegistry;
use Feather\Settings\SettingsExporter;
use Feather\Settings\SettingsImporter;
use Feather\Settings\SettingsRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Read & write Feather's top-level settings (preset, theme, opt-in flags).
 *
 * The features map is intentionally NOT writable through this endpoint —
 * use FeaturesEndpoint for that. This keeps the validation surface narrow.
 */
final class SettingsEndpoint implements RouteRegistrar {

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Feature registry — used when validating imports.
	 *
	 * @var FeatureRegistry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings.
	 * @param FeatureRegistry    $registry Feature registry.
	 */
	public function __construct( SettingsRepository $settings, FeatureRegistry $registry ) {
		$this->settings = $settings;
		$this->registry = $registry;
	}

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'read_settings' ),
					'permission_callback' => array( $this, 'permission_check' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'write_settings' ),
					'permission_callback' => array( $this, 'permission_check' ),
					'args'                => array(
						'preset'       => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_preset' ),
						),
						'theme'        => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_theme' ),
						),
						'usage_opt_in' => array(
							'type'     => 'boolean',
							'required' => false,
						),
						'optimizers_paused' => array(
							'type'     => 'boolean',
							'required' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_settings' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_settings' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_settings' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'payload' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/heartbeat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'write_heartbeat' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'frontend_disabled' => array( 'type' => 'boolean', 'required' => false ),
					'editor_interval'   => array( 'type' => 'integer', 'required' => false ),
					'admin_interval'    => array( 'type' => 'integer', 'required' => false ),
				),
			)
		);
	}

	/**
	 * GET /settings/export.
	 *
	 * Returns a JSON document a user can save and re-import on another site.
	 * The Content-Disposition header asks the browser to treat the response
	 * as a download.
	 */
	public function export_settings( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$exporter = new SettingsExporter( $this->settings );
		$response = new WP_REST_Response( $exporter->export(), 200 );
		$response->header( 'Content-Disposition', 'attachment; filename="feather-settings-' . gmdate( 'Ymd-His' ) . '.json"' );
		return $response;
	}

	/**
	 * POST /settings/import.
	 *
	 * Accepts the export shape under the `payload` parameter.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_settings( WP_REST_Request $request ) {
		$payload = $request->get_param( 'payload' );
		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				'feather_invalid_import',
				__( 'Import payload must be the contents of a Feather settings export file.', 'feather-performance' ),
				array( 'status' => 400 )
			);
		}

		$importer = new SettingsImporter( $this->settings, $this->registry );
		$issues   = $importer->validate( $payload );
		if ( ! empty( $issues ) ) {
			return new WP_Error(
				'feather_import_rejected',
				implode( ' ', $issues ),
				array(
					'status' => 400,
					'issues' => $issues,
				)
			);
		}

		$summary = $importer->apply( $payload );
		return new WP_REST_Response( $summary, 200 );
	}

	/**
	 * POST /settings/heartbeat — write per-context heartbeat overrides.
	 */
	public function write_heartbeat( WP_REST_Request $request ): WP_REST_Response {
		$advanced = array();
		foreach ( array( 'frontend_disabled', 'editor_interval', 'admin_interval' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) {
				$advanced[ $key ] = $value;
			}
		}
		$this->settings->set_heartbeat_advanced( $advanced );
		return new WP_REST_Response( $this->settings->heartbeat_advanced(), 200 );
	}

	/**
	 * POST /settings/reset.
	 *
	 * Reset settings to defaults (does not touch scan/metrics tables).
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function reset_settings( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$this->settings->reset();
		$all = $this->settings->all();
		unset( $all['features'] );
		return new WP_REST_Response( $all, 200 );
	}

	/**
	 * Permission callback.
	 */
	public function permission_check(): bool {
		return Capability::user_can();
	}

	/**
	 * Validate the preset value.
	 *
	 * @param mixed $value Incoming value.
	 */
	public function validate_preset( $value ): bool {
		return in_array(
			$value,
			array(
				SettingsRepository::PRESET_CONSERVATIVE,
				SettingsRepository::PRESET_BALANCED,
				SettingsRepository::PRESET_AGGRESSIVE,
				SettingsRepository::PRESET_CUSTOM,
			),
			true
		);
	}

	/**
	 * Validate the theme value.
	 *
	 * @param mixed $value Incoming value.
	 */
	public function validate_theme( $value ): bool {
		return in_array(
			$value,
			array(
				SettingsRepository::THEME_SYSTEM,
				SettingsRepository::THEME_LIGHT,
				SettingsRepository::THEME_DARK,
			),
			true
		);
	}

	/**
	 * GET /settings.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function read_settings( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$all = $this->settings->all();
		// Don't expose the features map here — FeaturesEndpoint owns that surface.
		unset( $all['features'] );

		return new WP_REST_Response( $all, 200 );
	}

	/**
	 * POST /settings.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function write_settings( WP_REST_Request $request ) {
		$current = $this->settings->all();

		$preset            = $request->get_param( 'preset' );
		$theme             = $request->get_param( 'theme' );
		$usage_opt_in      = $request->get_param( 'usage_opt_in' );
		$optimizers_paused = $request->get_param( 'optimizers_paused' );

		if ( is_string( $preset ) ) {
			$current['preset'] = $preset;
		}
		if ( is_string( $theme ) ) {
			$current['theme'] = $theme;
		}
		if ( is_bool( $usage_opt_in ) ) {
			$current['usage_opt_in'] = $usage_opt_in;
		}
		if ( is_bool( $optimizers_paused ) ) {
			$current['optimizers_paused'] = $optimizers_paused;
		}

		$this->settings->save( $current );

		$response = $current;
		unset( $response['features'] );

		return new WP_REST_Response( $response, 200 );
	}
}
