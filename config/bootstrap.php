<?php
/**
 * Global bootstrap: headers, session, security, config.
 * Ensures a session is active before any output so CSRF can render safely.
 *
 * @var array<string, mixed> $config
 *
 * @package AMPBoard
 * @author  Pawel Osmolski
 * @license GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once __DIR__ . '/autoload.php';
$requestOrigin = new \AMPBoard\Http\RequestOrigin( $_SERVER );
$session = new \AMPBoard\Http\NativeSession( $requestOrigin->isSecure() );
$session->start();

// Load core bits after session is up
require_once __DIR__ . '/config.php';
//include __DIR__ . '/debug.php';
include $config['paths']['partials'] . '/submit.php';
