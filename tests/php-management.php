<?php
/** Standalone process and temporary INI fixtures; no installed PHP files are edited. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
use AMPBoard\Php\SettingsInput;
use AMPBoard\Php\IniFile;
use AMPBoard\Php\InfoPage;
use AMPBoard\Php\Runtime;

function checkPhp( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function rejectPhp( callable $call ): void {
	try { $call(); } catch ( Throwable $error ) { return; }
	throw new RuntimeException( 'Expected invalid input rejection' );
}
$input = new SettingsInput();
$values = $input->normalise( [ 'displayPhpErrors' => 'on', 'logPhpErrors' => 'yes', 'phpErrorLevel' => 'E_WARNING',
	'phpMemoryLimit' => ' 256m ', 'phpMaxExecution' => '-1', 'phpMaxInputVars' => '3000', 'phpUploadMaxFile' => '20m',
	'phpPostMaxSize' => '50M', 'phpTimezone' => 'Europe/London', 'error_reporting_value' => 'E_ALL & ~E_NOTICE' ] );
checkPhp( $values['php'] === [ 'display_errors' => '1', 'log_errors' => '1', 'error_reporting' => E_WARNING,
	'memory_limit' => '256M', 'max_execution_time' => -1, 'max_input_vars' => 3000, 'upload_max_filesize' => '20M',
	'post_max_size' => '50M', 'date.timezone' => 'Europe/London' ], 'Existing normalized runtime values' );
checkPhp( ! isset( $values['ini']['log_errors'] ) && $values['ini']['display_errors'] === 'On' && $values['ini']['error_reporting'] === 'E_ALL & ~E_NOTICE', 'INI-specific legacy controls retained' );
checkPhp( $input->normalise( [ 'displayPhpErrors' => 'ON', 'phpMemoryLimit' => '100', 'phpMaxInputVars' => '0' ] )['php'] ===
	[ 'display_errors' => '0', 'log_errors' => '0', 'error_reporting' => E_ALL ], 'Invalid/omitted fields retain existing fallback behavior' );
checkPhp( SettingsInput::size( '-1', true, true ) === '-1' && SettingsInput::integer( '1.9' ) === 1, 'Historical normalization preserved' );
rejectPhp( static function () use ( $input ) { $input->normalise( [ 'error_reporting_value' => "E_ALL\nextension=other" ] ); } );
rejectPhp( static function () use ( $input ) { $input->normalise( [ 'phpMemoryLimit' => [] ] ); } );

$root = sys_get_temp_dir() . '/ampboard-php-' . bin2hex( random_bytes( 8 ) ); mkdir( $root, 0700 );
register_shutdown_function( static function () use ( $root ): void {
	foreach ( glob( $root . '/*' ) as $file ) { unlink( $file ); } rmdir( $root );
} );
$path = $root . '/php.ini';
$original = "; memory_limit = commented\r\n[PHP]\r\nMEMORY_LIMIT = 128M\r\nmemory_limit = 64M\r\nunrelated = keep\r\n[PATH=/srv/app]\r\nmemory_limit = 32M\r\n[HOST=example.test]\r\ndisplay_errors = On\r\n";
file_put_contents( $path, $original ); chmod( $path, 0640 );
$ini = new IniFile( $path );
checkPhp( $ini->patch( [ 'memory_limit' => '256M', 'display_errors' => 'Off', 'extension' => 'ignored' ] ), 'INI edit succeeds' );
$updated = file_get_contents( $path );
checkPhp( str_starts_with( $updated, "display_errors = Off\r\n" ) && str_contains( $updated, "MEMORY_LIMIT = 256M\r\nmemory_limit = 256M" ), 'Duplicate global directives updated; missing global inserted before sections' );
checkPhp( str_contains( $updated, "[PATH=/srv/app]\r\nmemory_limit = 32M" ) && str_contains( $updated, "[HOST=example.test]\r\ndisplay_errors = On" ), 'Scoped overrides preserved' );
checkPhp( str_contains( $updated, '; memory_limit = commented' ) && str_contains( $updated, 'unrelated = keep' ) && ! str_contains( $updated, 'extension' ), 'Unrelated lines and directive allowlist' );
if ( PHP_OS_FAMILY !== 'Windows' ) { clearstatcache(); checkPhp( ( fileperms( $path ) & 0777 ) === 0640, 'Unix mode preserved' ); }
checkPhp( $ini->patch( [ 'memory_limit' => '256M', 'display_errors' => 'Off' ] ) && file_get_contents( $path ) === $updated, 'Repeated edit stable' );
checkPhp( ! $ini->patch( [ 'error_reporting' => "E_ALL\nextension=x" ] ) && file_get_contents( $path ) === $updated, 'Invalid edit leaves original intact' );
$failing = new class( $path ) extends IniFile { protected function replace( string $from, string $to ): bool { return false; } };
checkPhp( ! $failing->patch( [ 'memory_limit' => '512M' ] ) && file_get_contents( $path ) === $updated && glob( $root . '/*.tmp' ) === [], 'Failed replacement preserves original and cleans staging' );
checkPhp( ! ( new IniFile( $root . '/missing.ini' ) )->patch( [ 'memory_limit' => '512M' ] ), 'Missing INI not created' );
$other = $root . '/other.ini'; file_put_contents( $other, 'memory_limit = 16M' );
checkPhp( ( new IniFile( $other ) )->patch( [ 'memory_limit' => '32M' ] ) && file_get_contents( $path ) === $updated, 'Independent file targets' );

$flags = [];
$page = new InfoPage( static function ( int $flag ) use ( &$flags ): void {
	$flags[] = $flag; echo '<html><head>discard</head><body><style>bad</style><table style="color:red"><tr><td>fixture</td></tr></table></body></html>';
} );
checkPhp( $page->render( false ) === '<table ><tr><td>fixture</td></tr></table>' && $page->render( true ) === '<table ><tr><td>fixture</td></tr></table>', 'Info layout filtering' );
checkPhp( $flags === [ INFO_ALL, INFO_GENERAL | INFO_CREDITS | INFO_LICENSE ], 'Full and demo flags remain distinct' );
$level = ob_get_level();
rejectPhp( static function () { ( new InfoPage( static function (): void { echo 'partial'; throw new RuntimeException(); } ) )->render( false ); } );
checkPhp( ob_get_level() === $level, 'Info capture cleans up on exception' );
$runtime = new Runtime();
$before = $runtime->values();
try {
	$runtime->apply( [ 'display_errors' => '0', 'error_reporting' => E_ERROR, 'date.timezone' => 'UTC', 'unmanaged' => 'ignored' ] );
	checkPhp( $runtime->values()['display_errors'] === '0' && error_reporting() === E_ERROR && date_default_timezone_get() === 'UTC', 'Runtime directives applied' );
	checkPhp( $runtime->inspect()['version'] === PHP_VERSION && $runtime->inspect()['sapi'] === PHP_SAPI, 'Current runtime inspected' );
} finally { $runtime->apply( $before ); }
echo "PASS PHP management services\n";
