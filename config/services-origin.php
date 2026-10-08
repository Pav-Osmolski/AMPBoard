<?php
/** Request headers only; no profile or session initialization. */
require_once __DIR__ . '/autoload.php';
$requestOrigin = new \AMPBoard\Http\RequestOrigin( $_SERVER );
