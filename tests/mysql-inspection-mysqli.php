<?php
/** Uses only the explicitly configured disposable CI database service. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
$host = getenv( 'AMPBOARD_TEST_DB_HOST' ); $user = getenv( 'AMPBOARD_TEST_DB_USER' ); $pass = getenv( 'AMPBOARD_TEST_DB_PASSWORD' );
if ( $host === false || $user === false || $pass === false ) { throw new RuntimeException( 'Configure a disposable test MySQL service.' ); }
$factory = new AMPBoard\Database\ConnectionFactory( [ 'host' => $host, 'user' => $user, 'pass' => $pass ] );
$driver = new mysqli_driver(); $originalMode = $driver->report_mode;
$name = 'ampboard_inspect_' . bin2hex( random_bytes( 8 ) ) . '`fixture';
$quoted = '`' . str_replace( '`', '``', $name ) . '`';
$admin = $factory->connect();
$connection = null;
$inspector = new AMPBoard\Database\Inspector( static function () use ( $factory, &$connection ) {
	return $connection = $factory->connect();
}, mysqli_get_client_info() );
try {
	mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
	$admin->query( "CREATE DATABASE $quoted" );
	$admin->query( "CREATE TABLE $quoted.fixture (id INT)" );
	foreach ( [ 0, 1, 3 ] as $mode ) {
		mysqli_report( $mode );
		foreach ( [ false, true ] as $fast ) {
			$report = $inspector->inspect( $fast );
			if ( $report['error'] !== null || $report['variables'] === null || $report['status'] === null || $report['processes'] === null ) {
				throw new RuntimeException( 'Real inspection failed.' );
			}
			$found = array_values( array_filter( $report['databases'], static function ( $db ) use ( $name ) { return $db['name'] === $name; } ) );
			if ( count( $found ) !== 1 || ( $fast ? $found[0]['sizeMb'] !== null : ! is_numeric( $found[0]['sizeMb'] ) ) ) {
				throw new RuntimeException( 'Quoted database size/mode mismatch.' );
			}
			if ( $driver->report_mode !== $mode ) { throw new RuntimeException( 'Inspection altered report flags.' ); }
			$closed = false;
			try { $connection->query( 'SELECT 1' ); } catch ( Error $error ) { $closed = true; }
			if ( ! $closed ) { throw new RuntimeException( 'Inspection did not close its connection.' ); }
		}
	}
} finally {
	try { $admin->query( "DROP DATABASE IF EXISTS $quoted" ); }
	finally { $admin->close(); mysqli_report( $originalMode ); }
}
echo "PASS real MySQL inspection, quoting, modes, and cleanup\n";
