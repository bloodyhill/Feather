<?php
/**
 * Drop the Elementor 4.0+ atomic widget JS chain on legacy-only pages.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;
use Feather\Scanner\ElementorPageDetector;
use Feather\Scanner\ScanRepository;
use Feather\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor 4.0 introduced an "atomic widgets" family (e-heading, e-button,
 * e-tabs, etc.) and a parallel JS bundle chain to drive them:
 *
 *   - elementor-v2-widgets-frontend (~atomic widget handler)
 *   - elementor-v2-frontend-handlers
 *   - elementor-v2-alpinejs            (Alpine.js, ~16KB gz)
 *   - elementor-tabs-handler           (only loaded when e-tabs is on the page)
 *   - elementor-youtube-handler        (only loaded when e-youtube is on the page)
 *
 * On Elementor pages that contain ONLY legacy widgets (heading, image-carousel,
 * the rivax-* widgets typical of 3.x-era themes), this chain is loaded but
 * unused. AtomicAssetGate dequeues it.
 *
 * Mirrors FrontendAssetGate's pattern: late wp_enqueue_scripts hook, scan-row
 * gated, conservative bail-outs in any ambiguous condition. Bails on Elementor
 * Pro because the theme-builder can inject atomic widgets via header/footer
 * templates that this post's scan row doesn't see.
 */
final class AtomicAssetGate extends AbstractOptimizer {

	/**
	 * Handles dequeued when the gate fires.
	 *
	 * @var string[]
	 */
	private const HANDLES = array(
		'elementor-v2-widgets-frontend',
		'elementor-v2-frontend-handlers',
		'elementor-v2-alpinejs',
		'elementor-tabs-handler',
		'elementor-youtube-handler',
	);

	/**
	 * @var ElementorPageDetector
	 */
	private $detector;

	/**
	 * @var ScanRepository
	 */
	private $repository;

	public function __construct( SettingsRepository $settings ) {
		parent::__construct( $settings );
		$this->detector   = new ElementorPageDetector();
		$this->repository = new ScanRepository();
	}

	public function id(): string {
		return 'f.elementor.skip_atomic_chain';
	}

	/**
	 * Bail when Elementor Pro is installed — its theme-builder can inject
	 * atomic widgets via header/footer templates the post scan doesn't see.
	 * Same stance as PerPageAssetTrimmer.
	 */
	public function is_safe(): bool {
		if ( class_exists( '\ElementorPro\Plugin' ) ) {
			return false;
		}
		if ( ! defined( '\ELEMENTOR_VERSION' ) ) {
			return false;
		}
		return version_compare( \ELEMENTOR_VERSION, '4.0', '>=' );
	}

	public function apply(): void {
		// Priority 9999 matches FrontendAssetGate / PerPageAssetTrimmer
		// so the three optimizers run in the same enqueue phase. They
		// don't conflict: the gate only acts on Elementor pages without
		// atomic widgets; FrontendAssetGate only on non-Elementor pages;
		// PerPageAssetTrimmer trims per-widget handles, not the v2 chain.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_dequeue' ), 9999 );
	}

	/**
	 * Drop the v4 chain when the queried post is Elementor-built but has
	 * no atomic widgets according to its scan row.
	 */
	public function maybe_dequeue(): void {
		if ( ! $this->detector->current_request_uses_elementor() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}

		// No scan row → can't prove the page is atomic-free; do nothing.
		$flags = $this->repository->flags_for_post( $post_id );
		if ( null === $flags ) {
			return;
		}

		if ( ! empty( $flags['has_atomic_widgets'] ) ) {
			return;
		}

		foreach ( self::HANDLES as $handle ) {
			wp_dequeue_script( $handle );
		}
	}
}
