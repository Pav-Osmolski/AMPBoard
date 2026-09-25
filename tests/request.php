<?php
/** Read-only request fixture; never connects to a real stack. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
// Isolate session GC from the runner's system session directory.
$sessionDirectory = sys_get_temp_dir() . '/ampboard-request-' . bin2hex( random_bytes( 8 ) );
mkdir( $sessionDirectory, 0700 );
session_save_path( $sessionDirectory );
// Exercise cleanup deterministically rather than waiting for random session GC.
ini_set( 'session.gc_probability', '1' );
ini_set( 'session.gc_divisor', '1' );
register_shutdown_function( static function () use ( $sessionDirectory ): void {
	if ( session_status() === PHP_SESSION_ACTIVE ) { session_write_close(); }
	foreach ( glob( $sessionDirectory . '/*' ) ?: [] as $file ) { unlink( $file ); }
	rmdir( $sessionDirectory );
} );
require __DIR__ . '/fixtures/database.php';
mysqli::$error = 'Fixture database unavailable';
$project = $argv[2] ?? dirname( __DIR__ );
$entry = $argv[1] ?? 'index.php';
$validPhp = str_ends_with( $entry, ':valid' );
$entry = str_replace( ':valid', '', $entry );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['USERNAME'] = 'ampboard-regression-fixture';
$_SERVER['USER'] = 'ampboard-regression-fixture';
$_SERVER['SCRIPT_NAME'] = '/ampboard/' . $entry;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['SERVER_SOFTWARE'] = 'Fixture Apache';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_GET['file'] = 'folders';
define( 'DEMO_MODE', true );
define( 'APACHE_PATH', __DIR__ . '/not-installed/apache' );
define( 'HTDOCS_PATH', __DIR__ . '/not-installed/htdocs' );
define( 'PHP_PATH', $validPhp ? __DIR__ : __DIR__ . '/not-installed/php' );
define( 'DB_HOST', 'fixture-host' );
define( 'DB_USER', 'fixture-user' );
define( 'DB_PASSWORD', 'fixture-pass' );
chdir( $project );
require $project . '/' . $entry;
