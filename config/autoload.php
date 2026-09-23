<?php
/** Load AMPBoard classes without requiring Composer on dashboard installations. */
spl_autoload_register( static function ( string $class ): void {
	$prefix = 'AMPBoard\\';
	if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file = __DIR__ . '/../src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( is_file( $file ) ) {
		require_once $file;
	}
} );
