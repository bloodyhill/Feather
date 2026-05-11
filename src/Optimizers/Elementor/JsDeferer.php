<?php
/**
 * Add `defer` to Elementor's frontend JavaScript handles.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites `<script src=…>` tags emitted by Elementor's known frontend
 * handles to include the `defer` attribute. Avoids touching tags that
 * already have `defer` or `async` so cache-plugin output is not broken.
 */
final class JsDeferer extends AbstractOptimizer {

	/**
	 * Handles eligible for defer.
	 *
	 * @var string[]
	 */
	private const DEFER_HANDLES = array(
		'elementor-frontend',
		'elementor-frontend-legacy',
		'elementor-common',
		'swiper',
		'e-lightbox',
	);

	public function id(): string {
		return 'f.elementor.defer_js';
	}

	public function apply(): void {
		add_filter( 'script_loader_tag', array( $this, 'maybe_add_defer' ), 10, 2 );
	}

	/**
	 * Inject `defer` for known Elementor handles.
	 *
	 * @param string $tag    Full script tag HTML.
	 * @param string $handle Script handle being filtered.
	 * @return string
	 */
	public function maybe_add_defer( $tag, $handle ) {
		if ( ! is_string( $tag ) || ! is_string( $handle ) ) {
			return $tag;
		}
		if ( ! in_array( $handle, self::DEFER_HANDLES, true ) ) {
			return $tag;
		}
		if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
			return $tag;
		}
		return str_replace( ' src=', ' defer src=', $tag );
	}
}
