<?php
/** Explicit compatibility boundary for existing trusted profiles and integrations. */
require_once __DIR__ . '/autoload.php';
if ( ! defined( 'AMPBOARD_NO_HELPERS' ) ) { require_once __DIR__ . '/helpers.php'; }
require_once __DIR__ . '/application.php';
$configLoader->publishLegacyConstants();
