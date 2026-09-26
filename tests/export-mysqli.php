<?php
/** Creates and drops its own randomly named database on the disposable CI MySQL service. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
$host = getenv( 'AMPBOARD_TEST_DB_HOST' ); $user = getenv( 'AMPBOARD_TEST_DB_USER' ); $pass = getenv( 'AMPBOARD_TEST_DB_PASSWORD' );
if ( $host === false || $user === false || $pass === false ) { throw new RuntimeException( 'Configure a disposable test MySQL service.' ); }
$factory = new \AMPBoard\Database\ConnectionFactory( [ 'host' => $host, 'user' => $user, 'pass' => $pass ] );
$exporter = new \AMPBoard\Export\DatabaseExporter( static function ( $name ) use ( $factory ) { return $factory->connect( [ 'db' => $name ] ); } );
$name = 'ampboard_export_' . bin2hex( random_bytes( 8 ) );
$admin = $factory->connect(); $source = null; $restored = null;
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
try {
	$admin->query( "CREATE DATABASE `$name` CHARACTER SET utf8mb4" );
	$source = $factory->connect( [ 'db' => $name ] );
	$source->query( 'CREATE TABLE fixture (id INT PRIMARY KEY, value TEXT NULL, bytes BLOB)' );
	$statement = $source->prepare( 'INSERT INTO fixture VALUES (?, ?, ?)' );
	for ( $i = 0; $i < 201; $i++ ) {
		$text = $i === 0 ? null : "quote' slash\\ newline\n café"; $bytes = "\0\x01\xff";
		$statement->bind_param( 'iss', $i, $text, $bytes ); $statement->execute();
	}
	$statement->close();
	$expected = $source->query( 'SELECT * FROM fixture ORDER BY id' )->fetch_all( MYSQLI_ASSOC );
	$sql = $exporter->dump( $name );
	$source->query( 'DROP TABLE fixture' );
	$restored = $factory->connect( [ 'db' => $name ] );
	$restored->multi_query( $sql );
	do { if ( $result = $restored->store_result() ) { $result->free(); } } while ( $restored->more_results() && $restored->next_result() );
	$actual = $restored->query( 'SELECT * FROM fixture ORDER BY id' )->fetch_all( MYSQLI_ASSOC );
	if ( $actual !== $expected || ! in_array( $name, $exporter->databases(), true ) ) { throw new RuntimeException( 'SQL export/restore mismatch' ); }
	echo "PASS MySQL export/restore round trip\n";
} finally {
	if ( $source !== null ) { $source->close(); }
	if ( $restored !== null ) { $restored->close(); }
	$admin->query( "DROP DATABASE IF EXISTS `$name`" ); $admin->close();
}
