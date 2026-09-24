<?php
/** CLI request fixture: actual submit handler, isolated profile and INI paths. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/helpers.php';
$root = $argv[1];
$scenario = $argv[2];
mkdir( $root . '/partials', 0750, true );
mkdir( $root . '/config' );
copy( __DIR__ . '/../partials/submit.php', $root . '/partials/submit.php' );
file_put_contents( $root . '/config/config.php', '<?php /* Dependencies supplied by the fixture. */' );
$profiles = new \AMPBoard\Config\ProfileRepository( $root . '/config', new \AMPBoard\Security\CredentialCipher( $root . '/.key' ) );
session_save_path( $root );
session_start();
$_SESSION['csrf_token'] = 'fixture-token';
$_SERVER = array_replace( $_SERVER, [ 'REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
	'CONTENT_LENGTH' => '100', 'HTTP_HOST' => 'localhost', 'HTTP_ORIGIN' => 'http://localhost', 'USERNAME' => 'request-user' ] );
$_POST = [ 'csrf' => 'fixture-token', 'theme' => 'dracula', 'folders_json' => '[{"title":"Saved"}]',
	'php_ini_path' => $root . '/php.ini', 'phpMemoryLimit' => '256M' ];
file_put_contents( $root . '/php.ini', "memory_limit = 128M\n" );
if ( $scenario === 'csrf' ) { $_POST['csrf'] = 'invalid'; }
if ( $scenario === 'origin' ) { $_SERVER['HTTP_ORIGIN'] = 'https://other.example'; }
if ( $scenario === 'json' ) { $_POST['folders_json'] = '{broken'; }
if ( $scenario === 'type' ) { $_SERVER['CONTENT_TYPE'] = 'application/json'; }
if ( $scenario === 'demo' ) { define( 'DEMO_MODE', true ); }
if ( $scenario === 'write' ) { file_put_contents( $root . '/config/profiles', 'blocked destination' ); }
ob_start();
register_shutdown_function( static function () use ( $root, $scenario, $profiles ): void {
	$body = ob_get_clean();
	$success = $scenario === 'valid';
	$status = $success || $scenario === 'demo' ? 303 : 400;
	$exists = is_file( $root . '/config/profiles/request-user/user_config.php' );
	$ok = http_response_code() === $status && $exists === $success;
	$ok = $ok && ( $status === 400 ? $body === 'Bad request.' : $body === '' );
	if ( $success ) {
		$loaded = $profiles->load( 'request-user' );
		$ok = $ok && $loaded['settings']['theme'] === 'dracula' && $loaded['profile']['folders'][0]['title'] === 'Saved';
	}
	$ini = file_get_contents( $root . '/php.ini' );
	$ok = $ok && str_contains( $ini, $success ? '256M' : '128M' );
	if ( session_status() === PHP_SESSION_ACTIVE ) { session_destroy(); }
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' submit ' . $scenario . "\n";
	exit( $ok ? 0 : 1 );
} );
require $root . '/partials/submit.php';
