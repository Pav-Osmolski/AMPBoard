<?php
/** Explicit certificates dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-apache-commands.php";

$certificates = new \AMPBoard\Certificates\Generator( $config['paths']['apache'], $config['paths']['crt'], PHP_OS_FAMILY, $apacheCommands );
