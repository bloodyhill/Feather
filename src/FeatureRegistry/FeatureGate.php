<?php
/**
 * Feature gate — routes per-feature recommendations using scan results.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\FeatureRegistry;

use Feather\Scanner\ScanRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Decides the badge a feature should show in the dashboard.
 *
 * Every feature registered in the registry is available; this class
 * only computes the recommendation surfaced in the UI based on the
 * static risk and (for `gated` features) the latest scan results.
 */
final class FeatureGate {

	public const RECOMMENDATION_SAFE      = 'safe';
	public const RECOMMENDATION_SCAN_REC  = 'scan_recommended';
	public const RECOMMENDATION_RISKY     = 'risky';
	public const RECOMMENDATION_DANGEROUS = 'dangerous';

	/**
	 * Feature registry.
	 *
	 * @var FeatureRegistry
	 */
	private $registry;

	/**
	 * Scan repository (used by recommendation_for()).
	 *
	 * @var ScanRepository
	 */
	private $scan_repository;

	/**
	 * Constructor.
	 *
	 * @param FeatureRegistry $registry         Feature registry.
	 * @param ScanRepository  $scan_repository  Scan repository.
	 */
	public function __construct(
		FeatureRegistry $registry,
		ScanRepository $scan_repository
	) {
		$this->registry        = $registry;
		$this->scan_repository = $scan_repository;
	}

	/**
	 * Whether a feature is available on this install.
	 *
	 * @param string $feature_id Feature id.
	 */
	public function is_unlocked( string $feature_id ): bool {
		return $this->registry->has( $feature_id );
	}

	/**
	 * Best-available recommendation for a feature, factoring scan results.
	 *
	 * Falls back to the static `risk` declared in features.php when no scan
	 * has run, or when the feature has no scan-aware logic.
	 *
	 * @param string $feature_id Feature id.
	 */
	public function recommendation_for( string $feature_id ): string {
		$metadata = $this->registry->get( $feature_id );
		if ( ! $metadata ) {
			return self::RECOMMENDATION_DANGEROUS;
		}

		$static = $this->static_to_recommendation( $metadata->risk() );

		// Only `gated` features benefit from a scan-derived verdict; others
		// just echo the static badge so the UI never shows a stale recommendation.
		if ( FeatureMetadata::RISK_GATED !== $metadata->risk() ) {
			return $static;
		}

		$aggregate = $this->scan_repository->aggregate();
		if ( empty( $aggregate['has_results'] ) ) {
			return self::RECOMMENDATION_SCAN_REC;
		}

		switch ( $feature_id ) {
			case 'f.elementor.eicons_global':
				$count = (int) ( $aggregate['eicons_usage_count'] ?? 0 );
				if ( 0 === $count ) {
					return self::RECOMMENDATION_SAFE;
				}
				return self::RECOMMENDATION_DANGEROUS;

			case 'f.elementor.unused_widget_bundles':
				// The optimizer self-protects per bundle: it only dequeues a
				// handle when no used widget needs it. Once a scan exists,
				// the feature is safe to enable — it figures out which
				// bundles to drop on its own.
				return self::RECOMMENDATION_SAFE;

			case 'f.elementor.google_fonts':
				$count = (int) ( $aggregate['google_fonts_usage_count'] ?? 0 );
				if ( 0 === $count ) {
					return self::RECOMMENDATION_SAFE;
				}
				return self::RECOMMENDATION_RISKY;
		}

		return self::RECOMMENDATION_SCAN_REC;
	}

	/**
	 * Map a feature's static risk constant to a recommendation constant.
	 */
	private function static_to_recommendation( string $risk ): string {
		switch ( $risk ) {
			case FeatureMetadata::RISK_RISKY:
				return self::RECOMMENDATION_RISKY;
			case FeatureMetadata::RISK_GATED:
				return self::RECOMMENDATION_SCAN_REC;
			case FeatureMetadata::RISK_SAFE:
			default:
				return self::RECOMMENDATION_SAFE;
		}
	}
}
