<?php
/** Explicit vhosts dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";

$vhosts = new \AMPBoard\Apache\VhostCatalog( $config['paths']['apache'], [
	getenv( 'WINDIR' ) ? getenv( 'WINDIR' ) . '/System32/drivers/etc/hosts' : '',
	'/etc/hosts',
] );
