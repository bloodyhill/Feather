<?php
/**
 * Auto-add width/height to <img> tags that are missing dimensions.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Media;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Reduces Cumulative Layout Shift by ensuring every <img> in `the_content`
 * declares explicit width/height attributes.
 *
 * WordPress core already adds these for media-library attachments via
 * `wp_filter_content_tags`. This optimizer covers images Elementor (and
 * other builders) emit that bypass that filter — typically background or
 * external images referenced by URL only.
 *
 * Strategy: if width/height are absent, attempt to look up dimensions for
 * any media-library attachment URL we recognise. External images we leave
 * alone (we don't make HTTP requests just to measure them).
 */
final class ImageDimensionsAdder extends AbstractOptimizer {

	public function id(): string {
		return 'f.media.image_dimensions';
	}

	public function apply(): void {
		add_filter( 'the_content', array( $this, 'add_dimensions' ), 99 );
	}

	/**
	 * Inject width/height into bare <img> tags where we can resolve them.
	 *
	 * @param mixed $html Filtered HTML.
	 * @return string
	 */
	public function add_dimensions( $html ): string {
		if ( ! is_string( $html ) || '' === $html || is_admin() ) {
			return is_string( $html ) ? $html : '';
		}
		if ( false === stripos( $html, '<img' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/<img\b([^>]*)>/i',
			array( $this, 'rewrite_img' ),
			$html
		) ?? $html;
	}

	/**
	 * Rewrite a single <img> tag if dimensions are missing.
	 *
	 * @param array<int, string> $matches Regex matches.
	 */
	private function rewrite_img( array $matches ): string {
		$attrs = $matches[1];
		// Preserve existing dimensions.
		if (
			preg_match( '/\swidth\s*=/i', $attrs )
			&& preg_match( '/\sheight\s*=/i', $attrs )
		) {
			return $matches[0];
		}

		if ( ! preg_match( '/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $src_match ) ) {
			return $matches[0];
		}
		$src = $src_match[1];

		$attachment_id = attachment_url_to_postid( $src );
		if ( $attachment_id <= 0 ) {
			return $matches[0];
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( ! is_array( $image ) || count( $image ) < 3 ) {
			return $matches[0];
		}

		$width  = (int) $image[1];
		$height = (int) $image[2];
		if ( $width <= 0 || $height <= 0 ) {
			return $matches[0];
		}

		// Build the new attribute string preserving everything except width/height.
		$attrs_clean = preg_replace( '/\s(width|height)\s*=\s*["\'][^"\']*["\']/i', '', $attrs );
		return sprintf( '<img%s width="%d" height="%d">', $attrs_clean, $width, $height );
	}
}
