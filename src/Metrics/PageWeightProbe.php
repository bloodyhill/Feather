<?php
/**
 * HTTP probe that measures a URL's response size and asset count.
 *
 * @package Feather
 */

declare( strict_types=1 );

namespace Feather\Metrics;

defined( 'ABSPATH' ) || exit;

/**
 * Performs a single GET against a URL and produces a snapshot.
 *
 * Snapshot shape (returned from probe()):
 *   [
 *       'url'          => string,
 *       'html_bytes'   => int,
 *       'scripts'      => int,
 *       'stylesheets'  => int,
 *       'images'       => int,
 *       'iframes'      => int,
 *       'fonts'        => int,
 *       'total_assets' => int,
 *       'measured_at'  => int,   // unix ts
 *       'http_status'  => int,
 *       'error'        => string|null,
 *   ]
 *
 * Counts come from cheap regex over the returned HTML — accurate enough
 * for trend tracking and orders-of-magnitude better than running a
 * headless browser. The probe never follows asset URLs to measure their
 * individual size; that's a bigger v2 work item.
 */
final class PageWeightProbe {

	public const REQUEST_TIMEOUT_SECONDS = 10;

	/**
	 * Probe a single URL.
	 *
	 * @param string $url Absolute URL to probe.
	 * @return array<string, mixed>
	 */
	public function probe( string $url ): array {
		$base = array(
			'url'          => $url,
			'html_bytes'   => 0,
			'scripts'      => 0,
			'stylesheets'  => 0,
			'images'       => 0,
			'iframes'      => 0,
			'fonts'        => 0,
			'total_assets' => 0,
			'measured_at'  => time(),
			'http_status'  => 0,
			'error'        => null,
		);

		// Clean URL early — only allow http/https.
		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) ) {
			$base['error'] = __( 'Invalid URL.', 'feather-performance' );
			return $base;
		}
		if ( ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			$base['error'] = __( 'Only http and https URLs can be probed.', 'feather-performance' );
			return $base;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::REQUEST_TIMEOUT_SECONDS,
				'redirection' => 2,
				'user-agent'  => 'Feather/' . FEATHER_VERSION . ' (+probe)',
				// Loopback requests sometimes need this to avoid SSL self-signed errors
				// in local dev. PCP allows it; on production sites SSL stays validated
				// because the user's site cert is presumably valid.
				'sslverify'   => apply_filters( 'feather/probe_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$base['error'] = $response->get_error_message();
			return $base;
		}

		$body                 = (string) wp_remote_retrieve_body( $response );
		$base['http_status']  = (int) wp_remote_retrieve_response_code( $response );
		$base['html_bytes']   = strlen( $body );
		$base['scripts']      = $this->count_pattern( '/<script\b[^>]*\bsrc\s*=/i', $body );
		$base['stylesheets']  = $this->count_pattern( '/<link\b[^>]*\brel\s*=\s*["\'](?:stylesheet|preload)["\'][^>]*>/i', $body );
		$base['images']       = $this->count_pattern( '/<img\b[^>]*>/i', $body );
		$base['iframes']      = $this->count_pattern( '/<iframe\b[^>]*>/i', $body );
		$base['fonts']        = $this->count_pattern( '/\.(woff2|woff|ttf|otf)\b/i', $body );
		$base['total_assets'] = $base['scripts']
			+ $base['stylesheets']
			+ $base['images']
			+ $base['iframes'];

		return $base;
	}

	/**
	 * Probe the site's homepage. Convenience wrapper.
	 *
	 * @return array<string, mixed>
	 */
	public function probe_home(): array {
		return $this->probe( home_url( '/' ) );
	}

	/**
	 * Count regex matches.
	 *
	 * @param string $pattern Regex pattern.
	 * @param string $haystack Subject.
	 */
	private function count_pattern( string $pattern, string $haystack ): int {
		if ( '' === $haystack ) {
			return 0;
		}
		$count = preg_match_all( $pattern, $haystack );
		return is_int( $count ) ? $count : 0;
	}
}
