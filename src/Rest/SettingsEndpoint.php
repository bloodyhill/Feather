<?php
/**
 * GET /settings, POST /settings
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
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
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings.
	 */
	public function __construct( SettingsRepository $settings ) {
		$this->settings = $settings;
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
