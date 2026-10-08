<?php
/** Explicit apache-log dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";

$apacheLog = new \AMPBoard\Logs\Viewer( \AMPBoard\Logs\Paths::apache( $config['paths']['apache'], PHP_OS_FAMILY, $_SERVER['HOME'] ?? '' ), new \AMPBoard\Logs\TailReader(), 'Apache', 5 );
