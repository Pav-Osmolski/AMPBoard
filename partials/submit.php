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

if ( ! $requestOrigin->isSameOrigin() ) {
	submit_fail( 'Failed same-origin check.' );
}

$csrf = $_POST['csrf'] ?? null;
if ( ! $csrfTokens->verify( is_string( $csrf ) ? $csrf : null ) ) {
	submit_fail( 'Invalid CSRF token.' );
}

/* ------------------------------------------------------------------ */
/* DEMO MODE guard                                                    */
/* ------------------------------------------------------------------ */

if ( $config['user']['isDemo'] ) {
	header( 'Location: ?view=settings&saved=0', true, 303 );
	exit;
}

try {
	$data = ( new \AMPBoard\Config\SettingsInput() )->normalise( $_POST );
	$profiles->save( $identity->user(), $data );
} catch ( \Throwable $error ) {
	submit_fail( $error->getMessage() );
}

// Optional php.ini editing remains best-effort, as before; profile saving is complete.
( $data['iniPath'] !== '' ? new \AMPBoard\Php\IniFile( $data['iniPath'] ) : $phpIni )->patch( $data['ini'] );

$session->regenerate();
header( 'Location: ?view=settings&saved=1', true, 303 );
exit;
