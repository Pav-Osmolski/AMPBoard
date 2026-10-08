<?php
/** Diagnostic lifetimes and historical failure policies; no live database or commands. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/fixtures/database.php';
require __DIR__ . '/../config/autoload.php';

use AMPBoard\Database\ConnectionFactory;
use AMPBoard\Database\Observation;
use AMPBoard\System\ServerInspector;

function checkObservation( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
$factory = new ConnectionFactory( [ 'host' => 'fixture', 'user' => 'fixture', 'pass' => '' ] );
$cases = [
	'' => [ true, true, true ],
	'Unknown host' => [ false, false, false ],
	'No route to host' => [ false, false, false ],
	"Can't connect to MySQL server" => [ false, false, false ],
	'Connection refused' => [ false, false, false ],
	'Access denied (using password: YES)' => [ true, true, false ],
	'Access denied (using password: NO)' => [ true, false, true ],
	'Access denied' => [ true, true, false ],
	'Unexpected server error' => [ true, false, false ],
];
foreach ( $cases as $error => $status ) {
	foreach ( [ false, true ] as $headerFirst ) {
		mysqli::$error = $error;
		$before = count( mysqli::$connections );
		$observation = new Observation( static function () use ( $factory ) { return $factory->connect( [ 'strictMode' => false ] ); } );
		checkObservation( count( mysqli::$connections ) === $before, 'Observation construction is lazy' );
		if ( $headerFirst ) { $observation->database(); }
		checkObservation( array_values( $observation->credentials() ) === $status, 'Historical credential result: ' . $error );
		checkObservation( $observation->database() === [ 'available' => $error === '', 'label' => $error === '' ? '8.0.36' : $error ], 'Header version/error result: ' . $error );
		checkObservation( $observation->credentials() === $observation->credentials() && count( mysqli::$connections ) === $before + 1, 'Both consumers and repeat reads share one attempt' );
		checkObservation( end( mysqli::$instances )->closed, 'Connection-error and success objects are closed' );
		checkObservation( array_values( $factory->credentialStatus() ) === $status, 'Legacy status API uses the same policy with a fresh attempt' );
		checkObservation( count( mysqli::$connections ) === $before + 2 && end( mysqli::$instances )->closed, 'Legacy API does not become a cross-call connection cache' );
	}
}
mysqli::$error = '';
$before = count( mysqli::$connections );
$first = new Observation( static function () use ( $factory ) { return $factory->connect( [ 'strictMode' => false ] ); } );
$second = new Observation( static function () use ( $factory ) { return $factory->connect( [ 'strictMode' => false ] ); } );
$first->credentials(); $second->database();
$operational = $factory->connect( [ 'db' => 'export-fixture' ] );
checkObservation( count( mysqli::$connections ) === $before + 3 && $operational->database === 'export-fixture' && ! $operational->closed, 'Separate requests and operational connections stay independent' );
$operational->close();
$commands = new class implements \AMPBoard\Apache\CommandRunner {
	public function run( string $command ): array { return [ 'success' => false, 'output' => '' ]; }
};
$inspector = new ServerInspector( new \AMPBoard\Apache\VersionProbe( '/absent', 'Fixture', '', $commands ), static function () { throw new RuntimeException( 'Header must use its supplied observation' ); }, [], $first );
checkObservation( $inspector->inspect()['database']['available'] && $inspector->inspect()['database']['label'] === '8.0.36' && count( mysqli::$connections ) === $before + 3, 'Header inspector uses the shared observation on repeated collection' );

mysqli::$failCharset = true;
foreach ( [ 0, 1, 3 ] as $mode ) {
	mysqli_driver::$mode = $mode;
	$failed = new Observation( static function () use ( $factory ) { return $factory->connect( [ 'strictMode' => false ] ); } );
	checkObservation( $failed->credentials() === [ 'host' => false, 'user' => false, 'pass' => false ] && $failed->database()['label'] === 'MySQL connection failed: charset failed', 'Charset failure preserves separate status and error semantics' );
	checkObservation( end( mysqli::$instances )->closed && mysqli_driver::$mode === $mode, 'Initialization failure closes the opened connection and restores reporting' );
}
mysqli::$failCharset = false;
mysqli::$failSelection = true;
try {
	$factory->connect( [ 'db' => 'unavailable-fixture' ] );
	throw new RuntimeException( 'Database selection must fail' );
} catch ( Exception $error ) {
	checkObservation( $error->getMessage() === 'MySQL connection failed: selection failed' && end( mysqli::$instances )->closed && mysqli_driver::$mode === 3, 'Database-selection failure closes its connection and preserves translation/reporting' );
} finally { mysqli::$failSelection = false; }
foreach ( [ new RuntimeException( 'Probe unavailable' ), new Error( 'Driver unavailable' ) ] as $error ) {
	$calls = 0;
	$failed = new Observation( static function () use ( &$calls, $error ) { ++$calls; throw $error; } );
	checkObservation( $failed->database() === [ 'available' => false, 'label' => $error->getMessage() ] && $failed->credentials() === [ 'host' => false, 'user' => false, 'pass' => false ] && $calls === 1, 'Unavailable probes are observed once' );
}
$closed = new Observation( static function () { return new class {
	public int $connect_errno = 0; public string $connect_error = ''; public string $server_info = '10.11.7-MariaDB-log';
	public function close(): void { throw new RuntimeException( 'Close failed' ); }
}; } );
checkObservation( $closed->database()['label'] === '10.11.7-MariaDB' && $closed->credentials()['host'], 'Cleanup failure does not erase a collected observation' );
passthru( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __DIR__ . '/database-driver-unavailable.php' ), $code );
checkObservation( $code === 0, 'Missing native driver is covered in a fresh process' );
echo "PASS shared database observations, credential policy and cleanup\n";
