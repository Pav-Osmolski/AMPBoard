<?php
/** Optional real-driver test. Use only a disposable test database. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
use AMPBoard\Database\ConnectionFactory;

function verify( bool $value, string $message ): void {
	if ( ! $value ) { throw new RuntimeException( $message ); }
}
verify( extension_loaded( 'mysqli' ), 'Enable the mysqli extension for this test.' );
$failureOnly = in_array( '--failure-only', $argv, true );
$host = $failureOnly ? '127.0.0.1:1' : getenv( 'AMPBOARD_TEST_DB_HOST' );
$user = $failureOnly ? 'fixture' : getenv( 'AMPBOARD_TEST_DB_USER' );
$pass = $failureOnly ? 'fixture' : getenv( 'AMPBOARD_TEST_DB_PASSWORD' );
verify( $host !== false && $user !== false && $pass !== false, 'Set AMPBOARD_TEST_DB_HOST, AMPBOARD_TEST_DB_USER and AMPBOARD_TEST_DB_PASSWORD for a disposable test database.' );
$factory = new ConnectionFactory( [ 'host' => $host, 'user' => $user, 'pass' => $pass ] );
$driver = new mysqli_driver();
$originalMode = $driver->report_mode;
try {
	foreach ( [ 0, 1, 3 ] as $mode ) {
		foreach ( [ false, true ] as $strict ) {
			mysqli_report( $mode );
			if ( ! $failureOnly ) {
				$conn = $factory->connect( [ 'strictMode' => $strict ] );
				verify( $conn->character_set_name() === 'utf8mb4', 'Connection charset' );
				verify( $driver->report_mode === $mode, 'Success restores report mode' );
				$conn->close();
			}
			$threw = false;
			try {
				$conn = $factory->connect( [ 'strictMode' => $strict, 'user' => 'ampboard_nonexistent_test_user', 'pass' => 'incorrect' ] );
				verify( $conn->connect_errno !== 0, 'Non-strict connection reports failure' );
			} catch ( Exception $e ) {
				$threw = true;
				verify( str_contains( $e->getMessage(), 'MySQL connection failed:' ), 'Connection exception translated' );
			}
			verify( $threw === $strict, 'Only strict failures throw' );
			verify( $driver->report_mode === $mode, 'Failure restores report mode' );
		}
	}
} finally {
	mysqli_report( $originalMode );
}
echo 'PASS real mysqli ' . ( $failureOnly ? 'failure paths' : 'success and failure paths' ) . "\n";
