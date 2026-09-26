<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/helpers.php';

use AMPBoard\Config\AtomicFileWriter;
use AMPBoard\Config\LegacyConstants;
use AMPBoard\Config\PhpSettings;
use AMPBoard\Config\ProfileReader;
use AMPBoard\Config\ProfileRepository;
use AMPBoard\Config\SettingsInput;
use AMPBoard\Security\CredentialCipher;

function check( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
function fails( callable $action, string $message ): void {
	$failed = false;
	try { $action(); } catch ( Throwable $error ) { $failed = true; }
	check( $failed, $message );
}
check( extension_loaded( 'openssl' ), 'Run this test with the OpenSSL extension enabled.' );
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );
$root = sys_get_temp_dir() . '/ampboard-profiles-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/config/profiles/default', 0750, true );
register_shutdown_function( static function () use ( $root ): void {
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) { $item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $root );
} );
$cipher = new CredentialCipher( $root . '/.key' );
$repository = new ProfileRepository( $root . '/config', $cipher );
$normalizer = new SettingsInput();
$default = [ 'version' => 1, 'settings' => [ 'theme' => 'overcast', 'displayHeader' => false, 'DB_USER' => 'default-user' ], 'php' => [] ];
file_put_contents( $root . '/config/profiles/default/user_config.php', '<?php return ' . var_export( $default, true ) . ';' );
file_put_contents( $root . '/config/profiles/default/folders.json', '[{"title":"Default"}]' );
$beforeConstants = LegacyConstants::read();
$fallback = $repository->load( 'alice' );
check( $fallback['settings']['theme'] === 'overcast' && $fallback['settings']['displayHeader'] === false, 'Defaults and false flags' );
check( $fallback['profile']['folders'][0]['title'] === 'Default', 'Default sidecars selected' );
check( ! file_exists( $root . '/.key' ), 'Plaintext default read does not create an encryption key' );

$input = [ 'DB_HOST' => 'localhost', 'DB_USER' => "alice'\\name", 'DB_PASSWORD' => 'password-$-quoted',
	'theme' => 'dracula', 'displayHeader' => 'on', 'displayPhpErrors' => '0', 'logPhpErrors' => 'yes',
	'phpMemoryLimit' => '256m', 'phpMaxExecution' => '30', 'phpTimezone' => 'Europe/London',
	'phpErrorLevel' => 'E_WARNING', 'APACHE_PATH' => 'C:/Apache/',
	'folders_json' => '[{"title":"User","dir":"projects/"}]', 'link_templates_json' => '{"example":"<b>OK</b>"}', 'dock_json' => '[]' ];
$data = $normalizer->normalise( $input );
check( $data['settings']['displayFooter'] === false && $data['php']['memory_limit'] === '256M', 'Form normalization' );
$repository->save( 'alice', $data );
$key = file_get_contents( $root . '/.key' );
check( preg_match( '/^[a-f0-9]{64}$/D', $key ) === 1, 'Existing key format retained' );
$stored = file_get_contents( $root . '/config/profiles/alice/user_config.php' );
check( ! str_contains( $stored, $input['DB_PASSWORD'] ), 'Password is not persisted in plaintext' );
$loaded = $repository->load( 'alice' );
check( $loaded['db'] === [ 'host' => 'localhost', 'user' => $input['DB_USER'], 'pass' => $input['DB_PASSWORD'] ], 'Credential save/load round trip' );
check( $loaded['profile'] === $data['profile'], 'Sidecar round trip' );
check( $loaded['php']['error_reporting'] === E_WARNING, 'PHP directives are data' );
check( LegacyConstants::read() === $beforeConstants, 'Modern profiles publish no constants' );
check( $repository->load( 'bob' )['db']['user'] === 'default-user', 'Independent profiles in one process' );
$data['settings']['DB_USER'] = 'bob';
$repository->save( 'bob', $data );
check( $repository->load( 'alice' )['db']['user'] === $input['DB_USER'], 'No cross-profile credential contamination' );
check( file_get_contents( $root . '/.key' ) === $key, 'Saving a second profile preserves the key' );

// Local returned arrays have explicit priority, including false values.
$local = [ 'version' => 1, 'settings' => [ 'DB_USER' => 'machine-user', 'displayHeader' => false, 'DEMO_MODE' => true ], 'php' => [] ];
file_put_contents( $root . '/config/local.php', '<?php return ' . var_export( $local, true ) . ';' );
$overridden = $repository->load( 'alice' );
check( $overridden['db']['user'] === 'machine-user' && $overridden['settings']['displayHeader'] === false, 'Local override precedence' );
unlink( $root . '/config/local.php' );

// Read ciphertext generated with the old IV+ciphertext format and unchanged key.
$iv = str_repeat( "\x01", 16 );
$legacyCiphertext = base64_encode( $iv . openssl_encrypt( 'old-secret', 'AES-256-CBC', hex2bin( $key ), OPENSSL_RAW_DATA, $iv ) );
check( $cipher->decrypt( $legacyCiphertext ) === 'old-secret', 'Historical ciphertext compatibility' );
check( $cipher->decrypt( $cipher->encrypt( '' ) ) === '', 'Empty encrypted value round trip' );
fails( static function () use ( $cipher ) { $cipher->decrypt( 'broken' ); }, 'Malformed ciphertext fails explicitly' );
$badCipher = new CredentialCipher( $root . '/bad.key' );
file_put_contents( $root . '/bad.key', 'legacy-or-corrupt' );
fails( static function () use ( $badCipher ) { $badCipher->encrypt( 'value' ); }, 'Invalid keys cannot be silently replaced' );
check( file_get_contents( $root . '/bad.key' ) === 'legacy-or-corrupt', 'Invalid key preserved' );
$missingCipher = new CredentialCipher( $root . '/absent.key' );
fails( static function () use ( $missingCipher, $legacyCiphertext ) { $missingCipher->decrypt( $legacyCiphertext ); }, 'Missing decryption key fails' );
check( ! file_exists( $root . '/absent.key' ), 'Decryption does not create keys' );

foreach ( [ [ 'version' => 2, 'settings' => [] ], [ 'version' => 1, 'settings' => [ 'displayHeader' => 'false' ] ],
	[ 'version' => 1, 'settings' => [], 'php' => [ 'unknown' => 'value' ] ] ] as $invalidProfile ) {
	file_put_contents( $root . '/invalid.php', '<?php return ' . var_export( $invalidProfile, true ) . ';' );
	fails( static function () use ( $root ) { ( new ProfileReader() )->read( $root . '/invalid.php' ); }, 'Reject unsupported profile data' );
}

foreach ( [ [ 'folders_json' => '{broken' ], [ 'DB_HOST' => 'bad host!' ], [ 'dock_json' => 'false' ], [ 'theme' => [] ] ] as $bad ) {
	fails( static function () use ( $normalizer, $input, $bad ) { $normalizer->normalise( array_replace( $input, $bad ) ); }, 'Reject invalid input' );
}
check( file_get_contents( $root . '/config/profiles/alice/user_config.php' ) === $stored, 'Invalid inputs leave the saved profile alone' );

// Inject an ordinary replacement failure after one sidecar has already committed.
$failingWriter = new class extends AtomicFileWriter {
	private int $calls = 0;
	protected function replace( string $from, string $to ): bool { return ++$this->calls !== 2 && parent::replace( $from, $to ); }
};
$failing = new ProfileRepository( $root . '/config', $cipher, $failingWriter );
$data['profile']['folders'] = [ [ 'title' => 'Must not survive failure' ] ];
fails( static function () use ( $failing, $data ) { $failing->save( 'alice', $data ); }, 'Failed commit reported' );
check( $repository->load( 'alice' )['profile'] === $loaded['profile'], 'Sidecars restored after partial commit failure' );
check( file_get_contents( $root . '/config/profiles/alice/user_config.php' ) === $stored, 'PHP profile remains unchanged on failure' );
$newFailure = new ProfileRepository( $root . '/config', $cipher, new class extends AtomicFileWriter {
	protected function replace( string $from, string $to ): bool { return false; }
} );
fails( static function () use ( $newFailure, $data ) { $newFailure->save( 'charlie', $data ); }, 'Failed first save reported' );
check( ! is_dir( $root . '/config/profiles/charlie' ) && $repository->load( 'charlie' )['active'] === $fallback['active'], 'First-save failure retains default fallback' );
check( glob( $root . '/config/profiles/*.pending.*' ) === [] && glob( $root . '/config/profiles/alice/*.tmp.*' ) === [], 'Staging cleanup' );

// Legacy local constants keep precedence; profile variables still override local defaults.
file_put_contents( $root . '/config/local.php', '<?php define("DB_HOST", "local-host"); $displayHeader = false;' );
mkdir( $root . '/config/profiles/legacy' );
file_put_contents( $root . '/config/profiles/legacy/user_config.php', '<?php if (!defined("DB_HOST")) { define("DB_HOST", "profile-host"); } define("DB_PASSWORD", ' . var_export( $legacyCiphertext, true ) . '); $theme="legacy"; $displayHeader=true;' );
$legacy = $repository->load( 'legacy' );
check( $legacy['db']['host'] === 'local-host' && $legacy['db']['pass'] === 'old-secret', 'Legacy constants and encrypted credentials load' );
check( $legacy['settings']['displayHeader'] === true, 'Legacy UI precedence unchanged' );
unlink( $root . '/config/local.php' );
$data['settings']['DB_PASSWORD'] = $legacy['db']['pass'];
$repository->save( 'legacy', $data );
$migrated = ( new ProfileReader() )->read( $root . '/config/profiles/legacy/user_config.php' );
check( ! $migrated['legacy'] && isset( $migrated['settings']['DB_PASSWORD']['encrypted'] ), 'Saving migrates legacy profile to returned array' );
check( $repository->load( 'legacy' )['db']['pass'] === 'old-secret', 'Migrated credential reloads' );
check( file_get_contents( $root . '/.key' ) === $key, 'Migration preserves existing key' );

$iniFile = $root . '/php.ini';
file_put_contents( $iniFile, "memory_limit = 128M\nunrelated = keep\n" );
check( ( new PhpSettings() )->patch( $iniFile, [ 'memory_limit' => '256M', 'display_errors' => 'Off' ] ), 'INI patch succeeds' );
check( str_contains( file_get_contents( $iniFile ), 'unrelated = keep' ) && str_contains( file_get_contents( $iniFile ), 'memory_limit = 256M' ), 'INI patch preserves unrelated settings' );
echo "PASS profile and credential integration\n";

foreach ( [ 'valid', 'csrf', 'origin', 'json', 'type', 'demo', 'write', 'ini-default', 'ini-failure', 'ini-injection' ] as $scenario ) {
	$process = proc_open( [ PHP_BINARY, '-n', __DIR__ . '/submit.php', $root . '/request-' . $scenario, $scenario ],
		[ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	check( is_resource( $process ), 'Start submit fixture' );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
	$errors = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
	$code = proc_close( $process );
	check( $code === 0 && str_starts_with( $output, 'PASS submit ' ), $output . $errors );
	echo $output;
}
