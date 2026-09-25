<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
if ( ( $argv[1] ?? '' ) === 'stdout' ) { echo 'stdout'; exit; }
if ( ( $argv[1] ?? '' ) === 'stderr' ) { fwrite( STDERR, 'stderr' ); exit( 7 ); }
require __DIR__ . '/../config/autoload.php';
$runner = new \AMPBoard\Apache\ShellCommandRunner();
// This fixture runs PHP output commands only, never any Apache command.
if ( ! function_exists( 'exec' ) && ! function_exists( 'proc_open' ) ) {
	$result = $runner->run( 'unused' );
	$ok = ! $result['success'] && $result['output'] === 'Command execution is unavailable.';
} else {
	$prefix = escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __FILE__ );
	$success = $runner->run( $prefix . ' stdout' );
	$failure = $runner->run( $prefix . ' stderr' );
	$ok = $success === [ 'output' => 'stdout', 'success' => true ] && $failure === [ 'output' => 'stderr', 'success' => false ];
}
echo ( $ok ? 'PASS' : 'FAIL' ) . ' command runner (disabled: ' . ini_get( 'disable_functions' ) . ")\n";
exit( $ok ? 0 : 1 );
