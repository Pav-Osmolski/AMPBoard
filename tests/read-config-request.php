<?php
/** Actual read-only handler with explicit request/profile data and no security helpers. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
$root = sys_get_temp_dir() . '/ampboard-read-request-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/utils', 0700, true ); mkdir( $root . '/config' );
copy( __DIR__ . '/../utils/read_config.php', $root . '/utils/read_config.php' );
file_put_contents( $root . '/config/config.php', '<?php /* supplied dependencies */' );
file_put_contents( $root . '/config/entry-read-config.php', '<?php require_once __DIR__ . "/config.php";' );

$scenario = $argv[1];
$_SERVER = [ 'REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'localhost:8080', 'HTTP_ORIGIN' => 'http://localhost:8080' ];
$_GET = [ 'file' => 'folders' ];
$config = [ 'profile' => [ 'folders' => [ [ 'title' => 'Fixture' ] ], 'linkTemplates' => [], 'dock' => [] ] ];
if ( $scenario === 'origin' ) { $_SERVER['HTTP_ORIGIN'] = 'http://other.test:8080'; }
if ( $scenario === 'method' ) { $_SERVER['REQUEST_METHOD'] = 'POST'; }
if ( $scenario === 'key' ) { $_GET['file'] = [ 'invalid' ]; }
$requestOrigin = new \AMPBoard\Http\RequestOrigin( $_SERVER );
ob_start();
register_shutdown_function( static function () use ( $root, $scenario ): void {
	$body = json_decode( ob_get_clean(), true );
	$expected = [ 'valid' => 200, 'origin' => 403, 'method' => 405, 'key' => 400 ][$scenario];
	$ok = ( http_response_code() ?: 200 ) === $expected && is_array( $body );
	$ok = $ok && ( $scenario === 'valid' ? ( $body[0]['title'] ?? '' ) === 'Fixture' : isset( $body['error'] ) );
	unlink( $root . '/utils/read_config.php' ); unlink( $root . '/config/entry-read-config.php' ); unlink( $root . '/config/config.php' );
	rmdir( $root . '/utils' ); rmdir( $root . '/config' ); rmdir( $root );
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' config request ' . $scenario . "\n"; exit( $ok ? 0 : 1 );
} );
require $root . '/utils/read_config.php';
