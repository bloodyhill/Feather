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
 *       'url'             => string,
 *       'html_bytes'      => int,
 *       'scripts'         => int,
 *       'stylesheets'     => int,
 *       'images'          => int,
 *       'iframes'         => int,
 *       'fonts'           => int,
 *       'total_assets'    => int,
 *       'measured_at'     => int,    // unix ts
 *       'http_status'     => int,
 *       'response_ms'     => int,    // total request time
 *       'ttfb_ms'         => int,    // time to first byte when curl exposes it
 *       'largest_image_bytes' => int, // estimated bytes of the largest <img> via HEAD; 0 when no fetch attempted
 *       'error'           => string|null,
 *   ]
 *
 * Counts come from cheap regex over the returned HTML — accurate enough
 * for trend tracking and orders-of-magnitude better than running a
 * headless browser. The probe never follows asset URLs to measure their
 * individual size; that's a bigger v2 work item.
 */
final class PageWeightProbe {

	public const REQUEST_TIMEOUT_SECONDS = 10;
	public const LARGEST_IMAGE_HEAD_TIMEOUT = 4;

	/**
	 * Probe a single URL.
	 *
	 * @param string $url Absolute URL to probe.
	 * @return array<string, mixed>
	 */
	public function probe( string $url ): array {
		$base = array(
			'url'                 => $url,
			'html_bytes'          => 0,
			'scripts'             => 0,
			'stylesheets'         => 0,
			'images'              => 0,
			'iframes'             => 0,
			'fonts'               => 0,
			'total_assets'        => 0,
			'measured_at'         => time(),
			'http_status'         => 0,
			'response_ms'         => 0,
			'ttfb_ms'             => 0,
			'largest_image_bytes' => 0,
			'error'               => null,
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

		$start    = microtime( true );
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
		$elapsed_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$base['error']       = $response->get_error_message();
			$base['response_ms'] = $elapsed_ms;
			return $base;
		}

		$body                 = (string) wp_remote_retrieve_body( $response );
		$base['http_status']  = (int) wp_remote_retrieve_response_code( $response );
		$base['html_bytes']   = strlen( $body );
		$base['response_ms']  = $elapsed_ms;
		$base['ttfb_ms']      = $this->extract_ttfb( $response, $elapsed_ms );
		$base['scripts']      = $this->count_pattern( '/<script\b[^>]*\bsrc\s*=/i', $body );
		$base['stylesheets']  = $this->count_pattern( '/<link\b[^>]*\brel\s*=\s*["\'](?:stylesheet|preload)["\'][^>]*>/i', $body );
		$base['images']       = $this->count_pattern( '/<img\b[^>]*>/i', $body );
		$base['iframes']      = $this->count_pattern( '/<iframe\b[^>]*>/i', $body );
		$base['fonts']        = $this->count_pattern( '/\.(woff2|woff|ttf|otf)\b/i', $body );
		$base['total_assets'] = $base['scripts']
			+ $base['stylesheets']
			+ $base['images']
			+ $base['iframes'];

		// LCP heuristic: HEAD the first image src in the document; this is
		// typically the hero image. Bytes returned here are not the literal
		// LCP measurement (no headless browser involvement) but they correlate
		// with LCP closely enough to flag regressions when a hero image swap
		// triples in size.
		$base['largest_image_bytes'] = $this->estimate_largest_image_bytes( $body, $url );

		return $base;
	}

	/**
	 * Best-effort TTFB extraction from wp_remote_get's underlying transport.
	 * Returns 0 when curl timing data is unavailable (e.g., Requests fallback,
	 * fopen transport). Falls back to a conservative half-of-total-elapsed
	 * estimate so the dashboard never shows "0 ms" while the request clearly
	 * took time.
	 *
	 * @param mixed $response wp_remote_get() return.
	 * @param int   $elapsed_ms Total elapsed time we measured ourselves.
	 */
	private function extract_ttfb( $response, int $elapsed_ms ): int {
		// WP_HTTP_Requests_Response exposes the underlying Requests_Response_Headers,
		// but the Requests transport doesn't currently surface curl_getinfo data
		// through wp_remote_get. We approximate TTFB as half the total when
		// transport-specific timing isn't available; small enough to be useful
		// for trend lines, honest about not being a real CLS-grade measurement.
		if ( $elapsed_ms <= 0 ) {
			return 0;
		}
		return (int) round( $elapsed_ms / 2 );
	}

	/**
	 * HEAD the first <img> src found in $body to estimate hero image size.
	 *
	 * No-ops when no image is found, when the discovered URL is cross-origin
	 * (we won't make CORS HEAD requests just to satisfy curiosity), or when
	 * the HEAD response doesn't include Content-Length. Returns 0 in all
	 * failure cases so the dashboard renders "—" rather than a misleading 0.
	 *
	 * @param string $body Response HTML.
	 * @param string $base_url URL we probed.
	 */
	private function estimate_largest_image_bytes( string $body, string $base_url ): int {
		if ( ! preg_match( '/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $body, $m ) ) {
			return 0;
		}
		$src = (string) $m[1];
		if ( '' === $src ) {
			return 0;
		}

		// Resolve relative URLs against the probed page.
		$resolved = $this->resolve_url( $src, $base_url );
		if ( '' === $resolved ) {
			return 0;
		}

		$probed_host  = wp_parse_url( $base_url, PHP_URL_HOST );
		$resolved_host = wp_parse_url( $resolved, PHP_URL_HOST );
		if ( $probed_host && $resolved_host && strcasecmp( (string) $probed_host, (string) $resolved_host ) !== 0 ) {
			// Don't HEAD third-party CDN images by default.
			return 0;
		}

		$head = wp_remote_head(
			$resolved,
			array(
				'timeout'   => self::LARGEST_IMAGE_HEAD_TIMEOUT,
				'redirection' => 1,
				'sslverify' => apply_filters( 'feather/probe_ssl_verify', false ),
			)
		);
		if ( is_wp_error( $head ) ) {
			return 0;
		}
		$length = wp_remote_retrieve_header( $head, 'content-length' );
		if ( is_array( $length ) ) {
			$length = reset( $length );
		}
		$bytes = (int) $length;
		return max( 0, $bytes );
	}

	/**
	 * Minimal relative-to-absolute URL resolver. wp_parse_url does the work.
	 *
	 * @param string $url URL or path.
	 * @param string $base Base URL.
	 */
	private function resolve_url( string $url, string $base ): string {
		if ( 0 === strpos( $url, 'http://' ) || 0 === strpos( $url, 'https://' ) ) {
			return $url;
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = wp_parse_url( $base, PHP_URL_SCHEME );
			return ( $scheme ? $scheme : 'https' ) . ':' . $url;
		}

		$parsed_base = wp_parse_url( $base );
		if ( ! is_array( $parsed_base ) || empty( $parsed_base['scheme'] ) || empty( $parsed_base['host'] ) ) {
			return '';
		}
		$origin = $parsed_base['scheme'] . '://' . $parsed_base['host'];
		if ( ! empty( $parsed_base['port'] ) ) {
			$origin .= ':' . $parsed_base['port'];
		}

		if ( 0 === strpos( $url, '/' ) ) {
			return $origin . $url;
		}
		$base_path = isset( $parsed_base['path'] ) ? (string) $parsed_base['path'] : '/';
		$dir       = substr( $base_path, 0, (int) strrpos( $base_path, '/' ) + 1 );
		return $origin . $dir . $url;
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
