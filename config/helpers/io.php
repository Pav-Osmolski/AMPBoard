<?php
/**
 * I/O helpers
 *
 * submit_fail(), atomic_write()
 *
 * @author  Pawel Osmolski
 * @version 1.0
 */

require_once __DIR__ . '/../autoload.php';

/**
 * Sends a generic 400 error without leaking sensitive details.
 *
 * @param string $msg
 *
 * @return void
 */
function submit_fail( string $msg ): void {
	\AMPBoard\Http\BadRequest::send( $msg );
}

/**
 * Writes content atomically with restrictive permissions.
 *
 * @param string $dstPath
 * @param string $content
 *
 * @return void
 */
function atomic_write( string $dstPath, string $content ): void {
	$dir = dirname( $dstPath );
	if ( ! is_dir( $dir ) ) {
		if ( ! mkdir( $dir, 0750, true ) ) {
			submit_fail( 'Failed to create directory: ' . $dir );
		}
	}

	$temp = $dstPath . '.tmp.' . bin2hex( random_bytes( 8 ) );
	if ( file_put_contents( $temp, $content, LOCK_EX ) === false ) {
		submit_fail( 'Failed to write temp file: ' . $temp );
	}
	@chmod( $temp, 0600 );

	if ( ! rename( $temp, $dstPath ) ) {
		@unlink( $temp );
		submit_fail( 'Failed to move temp into place: ' . $dstPath );
	}
}
