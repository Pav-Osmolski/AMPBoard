<?php
/** Explicit statistics dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-apache-commands.php";

$systemStatistics = new \AMPBoard\System\Statistics( PHP_OS_FAMILY, '/', $apacheCommands, [ new \AMPBoard\System\NativeMetrics(), 'read' ] );
