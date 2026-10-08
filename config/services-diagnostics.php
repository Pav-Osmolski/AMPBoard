<?php
/** Explicit diagnostics dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-database-observation.php";

$databaseStatus = $databaseObservation->credentials();
foreach ( [ 'host' => 'mySqlHostValid', 'user' => 'mySqlUserValid', 'pass' => 'mySqlPassValid' ] as $key => $field ) { $config['status'][$field] = $databaseStatus[$key]; }
