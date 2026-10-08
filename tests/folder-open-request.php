<?php
/** Actual endpoint guards; these requests cannot reach a desktop launch. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
$_SERVER['REQUEST_METHOD'] = $argv[1];
ob_start(); require __DIR__ . '/../utils/open_folder.php'; $body = ob_get_clean();
$expected = $_SERVER['REQUEST_METHOD'] === 'POST'
	? [ 'status' => 400, 'body' => [ 'error' => 'Invalid or missing folder path.' ] ]
	: [ 'status' => 405, 'body' => [ 'error' => 'Invalid request method.' ] ];
$ok = http_response_code() === $expected['status'] && json_decode( $body, true ) === $expected['body'];
echo ( $ok ? 'PASS' : 'FAIL' ) . ' folder endpoint ' . $_SERVER['REQUEST_METHOD'] . "\n";
exit( $ok ? 0 : 1 );
