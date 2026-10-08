<?php
/** Explicit database dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";

$database = new \AMPBoard\Database\ConnectionFactory( $config['db'] );
