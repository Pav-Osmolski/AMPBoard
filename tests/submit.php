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
$identity = new \AMPBoard\System\Identity( $_SERVER, static function () { throw new RuntimeException( 'Unexpected discovery' ); } );
// A later environment change must not redirect a save to another profile.
$_SERVER['USERNAME'] = 'changed-after-loading';
$_POST = [ 'csrf' => 'fixture-token', 'theme' => 'dracula', 'folders_json' => '[{"title":"Saved"}]',
	'php_ini_path' => $root . '/php.ini', 'phpMemoryLimit' => '256M' ];
file_put_contents( $root . '/php.ini', "memory_limit = 128M\n" );
$phpIni = new \AMPBoard\Php\IniFile( $root . '/php.ini' );
if ( $scenario === 'ini-default' || $scenario === 'ini-failure' ) { unset( $_POST['php_ini_path'] ); }
if ( $scenario === 'ini-failure' ) {
	$phpIni = new class( $root . '/php.ini' ) extends \AMPBoard\Php\IniFile {
		protected function replace( string $from, string $to ): bool { return false; }
	};
}
if ( $scenario === 'ini-injection' ) { $_POST['error_reporting_value'] = "E_ALL\nextension=other"; }
if ( $scenario === 'csrf' ) { $_POST['csrf'] = 'invalid'; }
if ( $scenario === 'port' ) { $_SERVER['HTTP_HOST'] = 'localhost:8080'; $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080'; }
if ( $scenario === 'origin' ) { $_SERVER['HTTP_ORIGIN'] = 'https://other.example'; }
if ( $scenario === 'json' ) { $_POST['folders_json'] = '{broken'; }
if ( $scenario === 'type' ) { $_SERVER['CONTENT_TYPE'] = 'application/json'; }
$config = [ 'user' => [ 'isDemo' => $scenario === 'demo' ] ];
define( 'DEMO_MODE', $scenario !== 'demo' );
if ( $scenario === 'write' ) { file_put_contents( $root . '/config/profiles', 'blocked destination' ); }
$requestOrigin = new \AMPBoard\Http\RequestOrigin( $_SERVER );
$session = new \AMPBoard\Http\NativeSession();
$csrfTokens = new \AMPBoard\Security\CsrfToken( $session );
if ( $scenario === 'session' ) { session_write_close(); session_save_path( $root . '/missing' ); }
$originalSessionId = session_id();
ob_start();
register_shutdown_function( static function () use ( $root, $scenario, $profiles, $originalSessionId ): void {
	$body = ob_get_clean();
	$success = in_array( $scenario, [ 'valid', 'port', 'ini-default', 'ini-failure' ], true );
	$status = $success || $scenario === 'demo' ? 303 : 400;
	$exists = is_file( $root . '/config/profiles/request-user/user_config.php' );
	$ok = http_response_code() === $status && $exists === $success;
	if ( $success ) { $ok = $ok && session_id() !== $originalSessionId; }
	$rotated = ! in_array( $scenario, [ 'session', 'csrf', 'origin', 'type' ], true );
	$ok = $ok && ( $scenario === 'session' ? session_status() !== PHP_SESSION_ACTIVE : ( ( $_SESSION['csrf_token'] ?? '' ) !== 'fixture-token' ) === $rotated );
	$ok = $ok && ( $status === 400 ? $body === 'Bad request.' : $body === '' );
	if ( $success ) {
		$loaded = $profiles->load( 'request-user' );
		$ok = $ok && $loaded['settings']['theme'] === 'dracula' && $loaded['profile']['folders'][0]['title'] === 'Saved';
	}
	$ini = file_get_contents( $root . '/php.ini' );
	$ok = $ok && str_contains( $ini, $success && $scenario !== 'ini-failure' ? '256M' : '128M' );
	$ok = $ok && glob( $root . '/*.tmp' ) === [];
	if ( session_status() === PHP_SESSION_ACTIVE ) { session_destroy(); }
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' submit ' . $scenario . "\n";
	exit( $ok ? 0 : 1 );
} );
require $root . '/partials/submit.php';
