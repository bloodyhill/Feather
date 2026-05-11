<?php
/**
 * Conservative Elementor-page detector.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether the current request is rendering an Elementor-built page.
 *
 * The detector errs on the side of "yes" — when in doubt, it returns true so
 * that conservative cleanup decisions never strip assets a real Elementor page
 * still needs. Ported from the legacy MU-plugin's `page_uses_elementor()`.
 */
final class ElementorPageDetector {

	/**
	 * Whether the current request needs Elementor frontend assets.
	 */
	public function current_request_uses_elementor(): bool {
		// If Elementor isn't loaded at all, the question is moot — but answering
		// "yes" keeps any theme-bundled Elementor reference happy.
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return true;
		}

		// Header / footer / theme-builder content always needs Elementor assets.
		if (
			did_action( 'elementor/theme/before_do_header' )
			|| did_action( 'elementor/theme/before_do_footer' )
		) {
			return true;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			// Archives, search, 404s, etc. — keep Elementor available.
			return true;
		}

		$elementor = \Elementor\Plugin::$instance ?? null;
		if ( null === $elementor || ! isset( $elementor->documents ) ) {
			return true;
		}

		$document = $elementor->documents->get( $post_id );
		if ( ! $document ) {
			return false;
		}

		return method_exists( $document, 'is_built_with_elementor' )
			? (bool) $document->is_built_with_elementor()
			: false;
	}
}
