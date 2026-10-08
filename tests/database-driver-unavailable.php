<?php
/** Deliberately no driver double: exercise real driver absence without a live connection. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
if ( extension_loaded( 'mysqli' ) ) { throw new RuntimeException( 'Run this fixture with php -n.' ); }
$factory = new \AMPBoard\Database\ConnectionFactory( [ 'host' => 'fixture', 'user' => 'fixture', 'pass' => '' ] );
$calls = 0;
$observation = new \AMPBoard\Database\Observation( static function () use ( $factory, &$calls ) { ++$calls; return $factory->connect( [ 'strictMode' => false ] ); } );
$invalid = [ 'host' => false, 'user' => false, 'pass' => false ];
if ( $factory->credentialStatus() !== $invalid || $observation->credentials() !== $invalid || $observation->database()['available'] || $observation->database()['label'] === '' || $calls !== 1 ) {
	throw new RuntimeException( 'Unavailable driver must become a stable diagnostic result.' );
}
echo "PASS database driver absence\n";
