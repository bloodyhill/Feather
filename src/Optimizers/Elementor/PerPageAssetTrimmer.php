<?php
/**
 * Trim per-widget Elementor assets to what the current page actually uses.
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
 * On Elementor-built pages, dequeue the per-widget `widget-*` stylesheets
 * (and a handful of widget-conditional scripts) that the page's scan row
 * doesn't list — turning Elementor's "register everything that's been ever
 * needed on this site" model into "load exactly what this URL needs."
 *
 * Safety rails (errs strongly toward "do nothing rather than break a page"):
 *
 *   1. Bails when Elementor Pro is installed. Pro adds a theme builder that
 *      can inject widgets from header/footer templates that the post's own
 *      scan row doesn't see — trimming there would dequeue assets a header
 *      widget needs.
 *   2. Bails when there's no `_elementor_page_assets` row for this post id
 *      (post never scanned, or it's an archive/search/404 with no singular
 *      target). FrontendAssetGate handles the non-Elementor case.
 *   3. Only touches handles starting with `widget-` plus an explicit allow-
 *      list of widget-conditional scripts (`swiper`, `jquery-numerator`).
 *      Globally-required handles (`elementor-frontend`, `elementor-common`,
 *      `elementor-post-css`, etc.) are never touched here — they're
 *      FrontendAssetGate's territory.
 */
final class PerPageAssetTrimmer extends AbstractOptimizer {

	/**
	 * Conditional script handles safe to trim when the scan row says the
	 * page doesn't use the widget that pulls them in.
	 *
	 * @var string[]
	 */
	private const TRIMMABLE_SCRIPTS = array(
		'swiper',
		'jquery-numerator',
		// Elementor 4.0+ atomic-element handlers. Loaded on every Elementor page
		// when the corresponding atomic element exists ANYWHERE on the site;
		// these dequeue calls drop them on pages that don't use them.
		// The v2 chain root handles (elementor-v2-widgets-frontend etc.) are
		// handled by AtomicAssetGate's all-or-nothing decision, not here.
		'elementor-tabs-handler',
		'elementor-youtube-handler',
	);

	/**
	 * Conditional non-`widget-*` style handles safe to trim.
	 *
	 * @var string[]
	 */
	private const TRIMMABLE_STYLES = array(
		'e-swiper',
		'e-apple-webkit',
	);

	/**
	 * Page detector.
	 *
	 * @var ElementorPageDetector
	 */
	private $detector;

	/**
	 * Scan repository.
	 *
	 * @var ScanRepository
	 */
	private $repository;

	public function __construct( SettingsRepository $settings ) {
		parent::__construct( $settings );
		$this->detector   = new ElementorPageDetector();
		$this->repository = new ScanRepository();
	}

	public function id(): string {
		return 'f.elementor.per_page_trim';
	}

	/**
	 * Don't trim when Elementor Pro is active — its theme-builder can pull
	 * widget assets from header/footer templates the post's scan row doesn't
	 * know about.
	 */
	public function is_safe(): bool {
		return ! class_exists( '\ElementorPro\Plugin' );
	}

	public function apply(): void {
		// Priority 9999 mirrors FrontendAssetGate so the two optimizers run
		// in the same enqueue phase. We don't conflict — the gate only acts
		// on non-Elementor pages, the trimmer only on Elementor pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_trim' ), 9999 );
	}

	/**
	 * Inspect the current request's enqueue queue and drop handles whose
	 * owning widget isn't on this page according to the scan row.
	 */
	public function maybe_trim(): void {
		if ( ! $this->detector->current_request_uses_elementor() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return;
		}

		$used = $this->repository->handles_for_post( $post_id );
		if ( null === $used ) {
			// No scan row — can't determine what's needed; do nothing.
			return;
		}

		global $wp_styles, $wp_scripts;

		if ( $wp_styles instanceof \WP_Styles ) {
			foreach ( $wp_styles->queue as $handle ) {
				if ( ! is_string( $handle ) ) {
					continue;
				}
				$is_widget_style = ( 0 === strpos( $handle, 'widget-' ) )
					|| in_array( $handle, self::TRIMMABLE_STYLES, true );
				if ( ! $is_widget_style ) {
					continue;
				}
				if ( in_array( $handle, $used, true ) ) {
					continue;
				}
				wp_dequeue_style( $handle );
			}
		}

		if ( $wp_scripts instanceof \WP_Scripts ) {
			foreach ( $wp_scripts->queue as $handle ) {
				if ( ! is_string( $handle ) ) {
					continue;
				}
				if ( ! in_array( $handle, self::TRIMMABLE_SCRIPTS, true ) ) {
					continue;
				}
				if ( in_array( $handle, $used, true ) ) {
					continue;
				}
				wp_dequeue_script( $handle );
			}
		}
	}
}
