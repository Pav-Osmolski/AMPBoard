<?php
/** Explicit server dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-database.php";
require_once __DIR__ . "/services-apache-commands.php";

$serverInspector = new \AMPBoard\System\ServerInspector(
	new \AMPBoard\Apache\VersionProbe( $config['paths']['apache'], PHP_OS_FAMILY, $_SERVER['SERVER_SOFTWARE'] ?? '', $apacheCommands ),
	static function () use ( $database ) { return $database->connect( [ 'strictMode' => false ] ); },
	$config['php']['runtime']
);
