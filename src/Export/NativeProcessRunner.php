<?php
namespace AMPBoard\Export;

final class NativeProcessRunner implements ProcessRunner {
	public function run( array $arguments, string $directory, string $input = '' ): array {
		if ( ! function_exists( 'proc_open' ) ) { return [ 'success' => false, 'output' => 'External command execution is unavailable.' ]; }
		$stdin = tmpfile(); $output = tmpfile();
		try {
			if ( $stdin === false || $output === false || fwrite( $stdin, $input ) !== strlen( $input ) ) { return [ 'success' => false, 'output' => 'Cannot capture command streams.' ]; }
			rewind( $stdin );
			$process = @proc_open( $arguments, [ 0 => $stdin, 1 => $output, 2 => $output ], $pipes, $directory, null, [ 'bypass_shell' => true ] );
			if ( ! is_resource( $process ) ) { return [ 'success' => false, 'output' => 'Cannot start archiver.' ]; }
			$status = proc_close( $process );
			rewind( $output );
			return [ 'success' => $status === 0, 'output' => (string) stream_get_contents( $output ) ];
		} finally {
			if ( is_resource( $stdin ) ) { fclose( $stdin ); }
			if ( is_resource( $output ) ) { fclose( $output ); }
		}
	}
}
