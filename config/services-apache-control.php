<?php
/** Explicit apache-control dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-apache-commands.php";

$apacheControl = new \AMPBoard\Apache\Controller( $config['paths']['apache'], PHP_OS_FAMILY, $apacheCommands );
