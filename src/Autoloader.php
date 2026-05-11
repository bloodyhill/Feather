<?php
/**
 * Feather PSR-4 autoloader.
 *
 * No Composer runtime dependency. Maps the `Feather\` namespace to
 * the directory this file lives in.
 *
 * @package Feather
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Feather\\';
		$base   = __DIR__ . '/';

		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = $base . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
