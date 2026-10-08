<?php
/** Validate the HTTP request, then save through the profile services. */

require_once __DIR__ . '/../config/entry-submit.php';

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
	\AMPBoard\Http\BadRequest::send( 'Invalid content type: ' . $ct );
}

$len = (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 );
if ( $len <= 0 ) {
	\AMPBoard\Http\BadRequest::send( 'Empty POST body.' );
}
if ( $len > 2 * 1024 * 1024 ) {
	\AMPBoard\Http\BadRequest::send( 'POST too large.' );
}

if ( ! $requestOrigin->isSameOrigin() ) {
	\AMPBoard\Http\BadRequest::send( 'Failed same-origin check.' );
}

$csrf = $_POST['csrf'] ?? null;
if ( ! $csrfTokens->verify( is_string( $csrf ) ? $csrf : null ) ) {
	\AMPBoard\Http\BadRequest::send( 'Invalid CSRF token.' );
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
	\AMPBoard\Http\BadRequest::send( $error->getMessage() );
}

// Optional php.ini editing remains best-effort, as before; profile saving is complete.
( $data['iniPath'] !== '' ? new \AMPBoard\Php\IniFile( $data['iniPath'] ) : $phpIni )->patch( $data['ini'] );

$session->regenerate();
header( 'Location: ?view=settings&saved=1', true, 303 );
exit;
