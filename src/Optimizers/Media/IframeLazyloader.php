<?php
/**
 * Add `loading="lazy"` to every <iframe> on the frontend.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Optimizers\Media;

use Feather\Optimizers\AbstractOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Walks `the_content` and any other major output filter and ensures every
 * <iframe> tag carries `loading="lazy"` (and `loading="eager"` is preserved
 * if explicitly set by another plugin).
 *
 * Skips tags that already declare a `loading` attribute so we never override
 * intent. Only operates on the public frontend.
 */
final class IframeLazyloader extends AbstractOptimizer {

	public function id(): string {
		return 'f.media.iframe_lazy';
	}

	public function apply(): void {
		add_filter( 'the_content', array( $this, 'maybe_lazy_iframes' ), 99 );
		add_filter( 'widget_text_content', array( $this, 'maybe_lazy_iframes' ), 99 );
		add_filter( 'comment_text', array( $this, 'maybe_lazy_iframes' ), 99 );
	}

	/**
	 * Inject loading="lazy" into bare <iframe> tags.
	 *
	 * @param mixed $html Filtered HTML.
	 * @return string
	 */
	public function maybe_lazy_iframes( $html ): string {
		if ( ! is_string( $html ) || '' === $html || is_admin() ) {
			return is_string( $html ) ? $html : '';
		}
		if ( $this->is_disabled_for_current_request() ) {
			return $html;
		}
		if ( false === stripos( $html, '<iframe' ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/<iframe\b([^>]*)>/i',
			static function ( array $matches ): string {
				$attrs = $matches[1];
				if ( preg_match( '/\sloading\s*=/i', $attrs ) ) {
					return $matches[0];
				}
				return '<iframe loading="lazy"' . $attrs . '>';
			},
			$html
		) ?? $html;
	}
}
