<?php
/** Validate the HTTP request, then save through the profile services. */

require_once __DIR__ . '/../config/config.php';

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
	// Safe no-op if included without a POST
	return;
}

/* ------------------------------------------------------------------ */
/* Basic request hardening                                            */
/* ------------------------------------------------------------------ */

$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if ( stripos( $ct, 'application/x-www-form-urlencoded' ) !== 0
	 && stripos( $ct, 'multipart/form-data' ) !== 0 ) {
	submit_fail( 'Invalid content type: ' . $ct );
}

$len = (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 );
if ( $len <= 0 ) {
	submit_fail( 'Empty POST body.' );
}
if ( $len > 2 * 1024 * 1024 ) {
	submit_fail( 'POST too large.' );
}

if ( ! function_exists( 'request_is_same_origin' ) || ! request_is_same_origin() ) {
	submit_fail( 'Failed same-origin check.' );
}

$csrf = $_POST['csrf'] ?? null;
if ( ! function_exists( 'csrf_verify' ) || ! csrf_verify( is_string( $csrf ) ? $csrf : null ) ) {
	submit_fail( 'Invalid CSRF token.' );
}

/* ------------------------------------------------------------------ */
/* DEMO MODE guard                                                    */
/* ------------------------------------------------------------------ */

if ( defined( 'DEMO_MODE' ) && DEMO_MODE ) {
	header( 'Location: ?view=settings&saved=0', true, 303 );
	exit;
}

try {
	$data = ( new \AMPBoard\Config\SettingsInput() )->normalise( $_POST );
	$profiles->save( resolveCurrentUser(), $data );
} catch ( \Throwable $error ) {
	submit_fail( $error->getMessage() );
}

// Optional php.ini editing remains best-effort, as before; profile saving is complete.
( new \AMPBoard\Config\PhpSettings() )->patch( $data['iniPath'] ?: ( php_ini_loaded_file() ?: '' ), $data['ini'] );

if ( session_status() !== PHP_SESSION_ACTIVE ) { session_start(); }
session_regenerate_id( true );
header( 'Location: ?view=settings&saved=1', true, 303 );
exit;
