<?php
/** Shared request diagnostics; operational connections remain independent. */
require_once __DIR__ . '/services-database.php';
$databaseObservation = new \AMPBoard\Database\Observation( static function () use ( $database ) { return $database->connect( [ 'strictMode' => false ] ); } );
