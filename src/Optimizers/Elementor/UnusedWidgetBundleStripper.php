<?php
/**
 * Drop Elementor sub-bundles when no widget on the site needs them.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;
use Feather\Scanner\ScanRepository;
use Feather\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The targeted-cleanup optimizer for Elementor's optional asset bundles.
 *
 * Elementor ships several sub-bundles on top of `elementor-frontend`:
 *
 *   - `swiper` (~140KB JS) — used by carousel/slides/gallery widgets
 *   - `e-lightbox` — used by image-box, gallery, video widgets
 *   - `lottie-js` — used by the Lottie widget
 *   - `elementor-animations` — used by entrance/headline animations
 *
 * When the user's site doesn't actually use any widget that depends on a
 * given bundle, that bundle is wasted bytes AND wasted CPU on every page
 * load. This optimizer reads the site-scan aggregate, intersects it with
 * the per-bundle widget requirements, and dequeues whichever bundles have
 * zero matching widgets in use site-wide.
 *
 * Conservative on purpose: a single matching widget anywhere on the site
 * keeps the bundle. Per-page targeting is a future enhancement.
 *
 * Requires a recent site scan to do anything — `is_safe()` returns false
 * when no scan results exist, so the optimizer becomes a no-op rather
 * than a false negative.
 */
final class UnusedWidgetBundleStripper extends AbstractOptimizer {

	/**
	 * Sub-bundle handle => widget types that need it.
	 *
	 * If the intersection of "widgets used on this site" and one of these
	 * lists is empty, the corresponding handle is dropped.
	 *
	 * Source of truth for THIS optimizer. The Scanner has its own
	 * widget-asset map (Scanner\WidgetAssetMap) which can drift slightly —
	 * this list is intentionally tighter and only covers handles we are
	 * confident dequeueing.
	 *
	 * @var array<string, string[]>
	 */
	private const BUNDLE_REQUIREMENTS = array(
		'swiper'                => array(
			'image-carousel',
			'slides',
			'media-carousel',
			'testimonial-carousel',
			'reviews',
			'gallery',
		),
		'e-lightbox'            => array(
			'image-box',
			'image-gallery',
			'gallery',
			'video',
			'video-playlist',
		),
		'elementor-animations'  => array(
			'animated-headline',
		),
		'lottie-js'             => array(
			'lottie',
		),
	);

	/**
	 * Scan repository (read-only access to the aggregate).
	 *
	 * @var ScanRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings repository.
	 */
	public function __construct( SettingsRepository $settings ) {
		parent::__construct( $settings );
		$this->repository = new ScanRepository();
	}

	public function id(): string {
		return 'f.elementor.unused_widget_bundles';
	}

	/**
	 * The optimizer is only meaningful once a scan exists. Without scan
	 * data, every bundle would be considered "unused" — that's a false
	 * negative that could break the site. Refuse to run.
	 */
	public function is_safe(): bool {
		$aggregate = $this->repository->aggregate();
		return ! empty( $aggregate['has_results'] );
	}

	public function apply(): void {
		// Late priority so we run after Elementor's own enqueue + after any
		// cache-plugin manipulations.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_dequeue_unused' ), 9999 );
	}

	/**
	 * For each sub-bundle whose required-widget list has no overlap with
	 * the site's used-widget list, dequeue both the script and the style
	 * variant of that handle.
	 */
	public function maybe_dequeue_unused(): void {
		$used = $this->used_widget_types();
		if ( null === $used ) {
			// No scan data — bail. is_safe() should have caught this earlier
			// but defending here too in case the optimizer is registered
			// directly by a third party.
			return;
		}

		foreach ( self::BUNDLE_REQUIREMENTS as $handle => $required_widgets ) {
			$intersection = array_intersect( $used, $required_widgets );
			if ( ! empty( $intersection ) ) {
				continue;
			}
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}

	/**
	 * The set of widget types that have at least one usage in the scan.
	 *
	 * Returns null if no scan has run.
	 *
	 * @return string[]|null
	 */
	private function used_widget_types(): ?array {
		$aggregate = $this->repository->aggregate();
		if ( empty( $aggregate['has_results'] ) ) {
			return null;
		}
		$counts = isset( $aggregate['widget_counts'] ) && is_array( $aggregate['widget_counts'] )
			? $aggregate['widget_counts']
			: array();

		$used = array();
		foreach ( $counts as $widget_type => $count ) {
			if ( is_string( $widget_type ) && (int) $count > 0 ) {
				$used[] = $widget_type;
			}
		}
		return $used;
	}
}
