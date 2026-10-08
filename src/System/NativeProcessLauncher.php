<?php
namespace AMPBoard\System;

/** Waits for the launch utility, with output isolated from the HTTP response. */
final class NativeProcessLauncher implements ProcessLauncher {
	public function launch( array $arguments ): bool {
		if ( $arguments === [] || ! function_exists( 'proc_open' ) || ! function_exists( 'proc_close' ) ) { return false; }
		$arguments = array_values( $arguments );
		foreach ( $arguments as $argument ) {
			if ( ! is_string( $argument ) || strpos( $argument, "\0" ) !== false ) { return false; }
		}
		if ( $arguments[0] === '' ) { return false; }
		$output = @tmpfile();
		if ( $output === false ) { return false; }
		try {
			$process = @proc_open( array_values( $arguments ), [ 0 => [ 'pipe', 'r' ], 1 => $output, 2 => $output ],
				$pipes, null, null, [ 'bypass_shell' => true ] );
			if ( ! is_resource( $process ) ) { return false; }
			fclose( $pipes[0] );
			return proc_close( $process ) === 0;
		} catch ( \Throwable $error ) {
			return false;
		} finally { fclose( $output ); }
	}
}
