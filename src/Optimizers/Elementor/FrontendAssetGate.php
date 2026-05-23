<?php
/**
 * Skip Elementor frontend assets on pages that aren't built with Elementor.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;
use Feather\Scanner\ElementorPageDetector;
use Feather\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The single biggest win in the legacy MU-plugin: when a request renders
 * something that wasn't built with Elementor (a blog post, a category
 * archive, WooCommerce shop pages with a non-Elementor template…), drop
 * Elementor's frontend bundle entirely.
 *
 * The detector errs on the side of "yes, Elementor is needed" so the theme
 * never breaks. This optimizer only acts in the negative case.
 */
final class FrontendAssetGate extends AbstractOptimizer {

	/**
	 * Handles dequeued when the page does NOT use Elementor.
	 *
	 * @var string[]
	 */
	private const HANDLES = array(
		'elementor-frontend',
		'elementor-frontend-legacy',
		'elementor-post-css',
		'swiper',
		'elementor-dialog',
		'elementor-waypoints',
		'elementor-animations',
		'elementor-common',
		'e-lightbox',
	);

	/**
	 * Page detector.
	 *
	 * @var ElementorPageDetector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Settings repository.
	 */
	public function __construct( SettingsRepository $settings ) {
		parent::__construct( $settings );
		$this->detector = new ElementorPageDetector();
	}

	public function id(): string {
		return 'f.elementor.skip_non_pages';
	}

	public function apply(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_dequeue' ), 9999 );
	}

	/**
	 * Drop Elementor frontend assets when this request doesn't need them.
	 */
	public function maybe_dequeue(): void {
		if ( $this->is_disabled_for_current_request() ) {
			return;
		}
		if ( $this->detector->current_request_uses_elementor() ) {
			return;
		}

		foreach ( self::HANDLES as $handle ) {
			wp_dequeue_style( $handle );
			wp_dequeue_script( $handle );
		}
	}
}
