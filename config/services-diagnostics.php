<?php
/** Explicit diagnostics dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-database.php";

$databaseStatus = $database->credentialStatus();
foreach ( [ 'host' => 'mySqlHostValid', 'user' => 'mySqlUserValid', 'pass' => 'mySqlPassValid' ] as $key => $field ) { $config['status'][$field] = $databaseStatus[$key]; }
