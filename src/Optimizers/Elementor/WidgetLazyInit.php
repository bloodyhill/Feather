<?php
/**
 * Enqueue the IntersectionObserver-based widget initializer.
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
 * Wires the front-end JS at `assets/js/widget-lazy-init.js` into Elementor
 * pages. The script holds Swiper, counter, video, and entrance-animation
 * initialization back until each section enters the viewport, while
 * above-the-fold content keeps the stock behaviour.
 *
 * The companion {@see JsDeferer} only adds the `defer` attribute to
 * Elementor's bundle; that solves blocking-during-HTML-parse but Elementor
 * still does all its widget work as soon as it boots. This optimizer is
 * the second leg — it defers the *work*, not just the parse.
 *
 * Gated to Elementor-rendered requests so blog/archive pages don't pay
 * the script weight. Loaded in the footer with `elementor-frontend` as a
 * dependency so it can call `elementorFrontend.elementsHandler` safely.
 */
final class WidgetLazyInit extends AbstractOptimizer {

	private const HANDLE = 'feather-widget-lazy-init';

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
		return 'f.elementor.widget_lazy_init';
	}

	public function apply(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 9999 );
	}

	/**
	 * Register and enqueue the lazy-init script on Elementor pages.
	 *
	 * Footer + `defer`: runs after DOMContentLoaded so it can query the
	 * full section list without observing a still-mutating DOM.
	 */
	public function enqueue(): void {
		if ( ! $this->detector->current_request_uses_elementor() ) {
			return;
		}

		$version = defined( 'FEATHER_VERSION' ) ? FEATHER_VERSION : '0.0.0';
		$src     = defined( 'FEATHER_URL' )
			? FEATHER_URL . 'assets/js/widget-lazy-init.js'
			: plugins_url( 'assets/js/widget-lazy-init.js', dirname( __DIR__, 3 ) . '/feather-performance.php' );

		wp_enqueue_script(
			self::HANDLE,
			$src,
			array( 'elementor-frontend' ),
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
