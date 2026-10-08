<?php
/** Explicit php-log dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";

$phpLog = new \AMPBoard\Logs\Viewer( [ $config['php']['runtime']['errorLog'] ], new \AMPBoard\Logs\TailReader(), 'PHP', 25, true );
