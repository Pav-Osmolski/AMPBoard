<?php
/** Explicit php-ini dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";

$phpIni = new \AMPBoard\Php\IniFile( $config['php']['runtime']['loadedIni'] );
