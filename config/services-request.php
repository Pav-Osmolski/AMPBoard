<?php
/** Explicit request dependencies; constructed once per request. */
require_once __DIR__ . '/services-origin.php';

$session = $session ?? new \AMPBoard\Http\NativeSession( $requestOrigin->isSecure() );
$csrfTokens = new \AMPBoard\Security\CsrfToken( $session );
