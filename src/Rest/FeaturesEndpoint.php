<?php
/**
 * GET /features, POST /features/{id}/toggle, POST /features/bulk
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Rest;

use Feather\Admin\Capability;
use Feather\FeatureRegistry\FeatureGate;
use Feather\FeatureRegistry\FeatureMetadata;
use Feather\FeatureRegistry\FeatureRegistry;
use Feather\Settings\SettingsRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Surface for browsing and toggling features from the React admin.
 *
 * GET    /features             — full list with current state
 * POST   /features/(?P<id>…)/toggle — flip one feature on/off
 * POST   /features/bulk        — apply a preset or batch update
 */
final class FeaturesEndpoint implements RouteRegistrar {

	/**
	 * Feature registry.
	 *
	 * @var FeatureRegistry
	 */
	private $registry;

	/**
	 * Feature gate.
	 *
	 * @var FeatureGate
	 */
	private $gate;

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param FeatureRegistry    $registry Feature registry.
	 * @param FeatureGate        $gate     Feature gate.
	 * @param SettingsRepository $settings Settings.
	 */
	public function __construct(
		FeatureRegistry $registry,
		FeatureGate $gate,
		SettingsRepository $settings
	) {
		$this->registry = $registry;
		$this->gate     = $gate;
		$this->settings = $settings;
	}

	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/features',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_features' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/features/(?P<id>[A-Za-z0-9_.\-]+)/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'toggle_feature' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'id'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( $this, 'sanitize_feature_id' ),
					),
					'enabled' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/features/bulk',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_update' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'updates' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission gate for every endpoint in this controller.
	 *
	 * @return bool
	 */
	public function permission_check() {
		return Capability::user_can();
	}

	/**
	 * Sanitize the feature id route parameter.
	 *
	 * @param mixed $value Incoming value.
	 * @return string
	 */
	public function sanitize_feature_id( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return preg_replace( '/[^A-Za-z0-9_.\-]/', '', $value ) ?? '';
	}

	/**
	 * GET /features.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public function list_features( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		// Return every feature including those marked hidden — the React
		// Features route filters them out client-side, while composite
		// Dashboard cards (which read individual state via this same list)
		// still get to see them.
		$features = array();
		foreach ( $this->registry->all() as $metadata ) {
			$features[] = $this->serialize( $metadata );
		}

		return new WP_REST_Response(
			array(
				'features' => $features,
				'preset'   => $this->settings->get( 'preset', SettingsRepository::PRESET_CONSERVATIVE ),
			),
			200
		);
	}

	/**
	 * POST /features/{id}/toggle.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle_feature( WP_REST_Request $request ) {
		$feature_id = (string) $request->get_param( 'id' );
		$enabled    = (bool) $request->get_param( 'enabled' );

		$metadata = $this->registry->get( $feature_id );
		if ( ! $metadata ) {
			return new WP_Error(
				'feather_unknown_feature',
				__( 'Unknown feature.', 'feather-performance' ),
				array( 'status' => 404 )
			);
		}

		$this->settings->set_enabled( $feature_id, $enabled );

		return new WP_REST_Response( $this->serialize( $metadata ), 200 );
	}

	/**
	 * POST /features/bulk.
	 *
	 * Body: { "updates": { "f.elementor.fa4_shim": true, "f.wp.emojis": false } }
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_update( WP_REST_Request $request ) {
		$updates = $request->get_param( 'updates' );
		if ( ! is_array( $updates ) ) {
			return new WP_Error(
				'feather_bad_payload',
				__( 'updates must be an object of feature_id => boolean.', 'feather-performance' ),
				array( 'status' => 400 )
			);
		}

		$applied = array();
		$skipped = array();

		foreach ( $updates as $feature_id => $enabled ) {
			if ( ! is_string( $feature_id ) ) {
				continue;
			}
			$metadata = $this->registry->get( $feature_id );
			if ( ! $metadata ) {
				$skipped[] = array(
					'id'     => $feature_id,
					'reason' => 'unknown',
				);
				continue;
			}
			$this->settings->set_enabled( $feature_id, (bool) $enabled );
			$applied[] = $feature_id;
		}

		return new WP_REST_Response(
			array(
				'applied' => $applied,
				'skipped' => $skipped,
			),
			200
		);
	}

	/**
	 * Build the REST representation of a feature.
	 *
	 * @param FeatureMetadata $metadata Feature metadata.
	 * @return array<string, mixed>
	 */
	private function serialize( FeatureMetadata $metadata ): array {
		$base                   = $metadata->to_array();
		$base['enabled']        = $this->settings->is_enabled( $metadata->id() );
		$base['recommendation'] = $this->gate->recommendation_for( $metadata->id() );
		return $base;
	}
}
