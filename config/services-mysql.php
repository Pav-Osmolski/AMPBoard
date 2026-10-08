<?php
/** Explicit mysql dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-database.php";

$mysqlInspector = new \AMPBoard\Database\Inspector(
	static function () use ( $database ) { return $database->connect(); },
	function_exists( 'mysqli_get_client_info' ) ? mysqli_get_client_info() : 'Unavailable'
);
