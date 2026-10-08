<?php
/** Explicit ui dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";

$ui = new \AMPBoard\Ui\Renderer( $config );
$demoMask = new \AMPBoard\Ui\DemoMask( $config['user']['isDemo'] );
