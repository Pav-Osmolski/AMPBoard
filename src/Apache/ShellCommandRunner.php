<?php

namespace AMPBoard\Apache;

/** Runs trusted diagnostics/control commands and reports unavailable execution explicitly. */
final class ShellCommandRunner implements CommandRunner {
	public function run( string $command ): array {
		if ( trim( $command ) === '' ) { return [ 'output' => '', 'success' => false ]; }
		if ( function_exists( 'exec' ) ) {
			$output = [];
			$status = 1;
			exec( $command . ' 2>&1', $output, $status );
			return [ 'output' => implode( "\n", $output ), 'success' => $status === 0 ];
		}
		if ( function_exists( 'proc_open' ) ) {
			// One temporary output stream avoids stdout/stderr pipe deadlocks.
			$output = tmpfile();
			if ( $output === false ) { return [ 'output' => 'Cannot capture command output.', 'success' => false ]; }
			try {
				$process = @proc_open( $command . ' 2>&1', [ 0 => [ 'pipe', 'r' ], 1 => $output, 2 => $output ], $pipes );
				if ( ! is_resource( $process ) ) { return [ 'output' => 'Cannot start command.', 'success' => false ]; }
				fclose( $pipes[0] );
				$status = proc_close( $process );
				rewind( $output );
				return [ 'output' => rtrim( stream_get_contents( $output ), "\r\n" ), 'success' => $status === 0 ];
			} finally { fclose( $output ); }
		}
		return [ 'output' => 'Command execution is unavailable.', 'success' => false ];
	}
}
