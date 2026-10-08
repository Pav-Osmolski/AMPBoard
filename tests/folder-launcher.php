<?php
/** Benign subprocess fixture: no desktop launcher is executed. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$mode = $argv[1] ?? '';
if ( $mode === 'arguments' ) {
	$ok = ( $argv[2] ?? '' ) === 'a & b %HOME% ! $ ` " quote' && ( $argv[3] ?? '' ) === 'Unicode é directory';
	echo 'captured stdout'; fwrite( STDERR, 'captured stderr' ); exit( $ok ? 0 : 3 );
}
if ( $mode === 'failure' ) { echo 'launcher failed'; exit( 7 ); }
require __DIR__ . '/../config/autoload.php';
$launcher = new \AMPBoard\System\NativeProcessLauncher();
$ok = ! $launcher->launch( [ 'unused-no-command-is-run' ] );
echo ( $ok ? 'PASS' : 'FAIL' ) . " disabled folder launcher\n"; exit( $ok ? 0 : 1 );
