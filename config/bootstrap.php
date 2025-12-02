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

if ( session_status() !== PHP_SESSION_ACTIVE ) {
	$secure = ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off';
	if ( function_exists( 'session_set_cookie_params' ) ) {
		$p = session_get_cookie_params();
		session_set_cookie_params( [
			'lifetime' => $p['lifetime'] ?? 0,
			'path'     => $p['path'] ?? '/',
			'domain'   => $p['domain'] ?? '',
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		] );
	}
	session_start();
}

// Load core bits after session is up
include __DIR__ . '/config.php';
//include __DIR__ . '/debug.php';
include $config['paths']['partials'] . '/submit.php';
