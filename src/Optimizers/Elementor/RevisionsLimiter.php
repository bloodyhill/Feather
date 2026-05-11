<?php
/**
 * Cap stored revisions on Elementor-built posts.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Elementor;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor saves a revision on every autosave. Over time a busy edit session
 * can produce dozens of revisions per post. This optimizer caps the count
 * for any post that has the `_elementor_data` meta — leaving non-Elementor
 * content unaffected.
 */
final class RevisionsLimiter extends AbstractOptimizer {

	public const REVISIONS_TO_KEEP = 5;

	public function id(): string {
		return 'f.elementor.revisions_cap';
	}

	public function apply(): void {
		add_filter( 'wp_revisions_to_keep', array( $this, 'cap_revisions' ), 10, 2 );
	}

	/**
	 * Apply the cap when the post is built with Elementor.
	 *
	 * @param int|mixed   $num  Currently configured revision count.
	 * @param \WP_Post|mixed $post Post object.
	 * @return int
	 */
	public function cap_revisions( $num, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return is_int( $num ) ? $num : (int) $num;
		}

		if ( ! metadata_exists( 'post', $post->ID, '_elementor_data' ) ) {
			return is_int( $num ) ? $num : (int) $num;
		}

		return self::REVISIONS_TO_KEEP;
	}
}
