<?php
/** JSON fixtures use temporary files and explicit readers; no real profiles change. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/fixtures/database.php';
require __DIR__ . '/../config/helpers/booleans.php';

use AMPBoard\Config\Json;
use AMPBoard\Filesystem\JsonReader;
use AMPBoard\Config\Loader;
use AMPBoard\Config\ProfileRepository;
use AMPBoard\Config\SettingsInput;
use AMPBoard\Security\CredentialCipher;
use AMPBoard\System\Identity;

function checkJson( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
function failsJson( callable $action, string $type ): void {
	try { $action(); } catch ( Throwable $error ) { checkJson( $error instanceof $type, 'Expected ' . $type ); return; }
	throw new RuntimeException( 'Expected JSON rejection: ' . $type );
}
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );
checkJson( ! function_exists( 'read_json_array_safely' ) && ! function_exists( 'validate_and_canonicalise_json' ), 'Namespaced fixtures run without JSON helpers' );
checkJson( Json::canonicalise( " \n\t" ) === '[]' && Json::canonicalise( '', false ) === '{}', 'Empty input defaults' );
checkJson( Json::canonicalise( '{}' ) === '[]' && Json::canonicalise( '[]' ) === '[]', 'Historical empty-object collapse retained' );
$raw = '{"path":"https://example.test/a","unicode":"é","number":1.0,"nested":{}}';
$expected = "{\n    \"path\": \"https://example.test/a\",\n    \"unicode\": \"\\u00e9\",\n    \"number\": 1,\n    \"nested\": []\n}";
checkJson( Json::canonicalise( $raw ) === $expected, 'Historical formatting, Unicode escapes, slash and number semantics' );
checkJson( Json::canonicalise( '{"0":"zero","1":"one"}' ) === "[\n    \"zero\",\n    \"one\"\n]", 'Sequential numeric object keys retain associative-decoding behavior' );
checkJson( Json::canonicalise( Json::canonicalise( $raw ) ) === $expected, 'Formatting is stable' );
foreach ( [ 'null', 'true', '123', '"text"' ] as $scalar ) { failsJson( static function () use ( $scalar ) { Json::canonicalise( $scalar ); }, InvalidArgumentException::class ); }
foreach ( [ '{', '[1,]', "[\"\xFF\"]", str_repeat( '[', 513 ) . '0' . str_repeat( ']', 513 ) ] as $invalid ) {
	failsJson( static function () use ( $invalid ) { Json::canonicalise( $invalid ); }, JsonException::class );
}
failsJson( static function () { Json::encode( ["\xFF"] ); }, JsonException::class );
$recursive = []; $recursive['self'] = &$recursive;
failsJson( static function () use ( &$recursive ) { Json::encode( $recursive ); }, JsonException::class ); unset( $recursive );

$root = sys_get_temp_dir() . '/ampboard-json-' . bin2hex( random_bytes( 8 ) );
mkdir( $root, 0700 );
register_shutdown_function( static function () use ( $root ): void {
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) { $item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $root );
} );
$messages = [];
$reader = new JsonReader( null, static function ( string $message ) use ( &$messages ) { $messages[] = $message; } );
checkJson( $reader->readArray( $root . '/missing.json' ) === [] && $reader->readArray( $root ) === [], 'Missing and non-file paths fall back' );
$file = $root . '/sample.json';
foreach ( [ '' => [], '[]' => [], '{}' => [], '[false,0,null,"é"]' => [false,0,null,'é'], '{"item":{"value":2}}' => ['item' => ['value' => 2]] ] as $text => $data ) {
	file_put_contents( $file, $text ); checkJson( $reader->readArray( $file ) === $data, 'Tolerant file decode: ' . $text );
}
checkJson( $messages === [], 'Missing and valid files produce no decode diagnostics' );
file_put_contents( $file, '{secret-invalid-payload' );
checkJson( $reader->readArray( $file ) === [] && $messages === ['sample.json JSON decode failed: Syntax error'], 'Malformed file fallback and basename-only diagnostic' );
file_put_contents( $file, 'null' );
checkJson( $reader->readArray( $file ) === [] && end( $messages ) === 'sample.json JSON decode failed: No error', 'Scalar-root historical diagnostic retained' );
file_put_contents( $file, "[\"\xFF\"]" );
checkJson( $reader->readArray( $file ) === [] && ! str_contains( end( $messages ), $root ), 'Invalid UTF-8 file fallback without full path disclosure' );
$deniedReads = 0; $deniedLogs = [];
$denied = new JsonReader( static function ( string $path ) use ( &$deniedReads ) { ++$deniedReads; return false; }, static function ( string $message ) use ( &$deniedLogs ) { $deniedLogs[] = $message; } );
checkJson( $deniedReads === 0, 'Reader construction performs no reads' );
checkJson( $denied->readArray( $file ) === [] && $deniedReads === 1 && $deniedLogs === [], 'Simulated unreadable/read-failure result is quiet and independent' );

// Independent repositories and loaders work without the global JSON helpers.
$snapshots = [];
foreach ( ['one', 'two'] as $name ) {
	$directory = $root . '/' . $name . '/config';
	mkdir( $directory . '/profiles/default', 0700, true );
	$paths = [];
	$source = new JsonReader( static function ( string $path ) use ( $name, &$paths ) {
		$paths[] = $path;
		return basename( $path ) === 'tooltips.json' ? '{"example":"' . $name . ' tooltip"}' : '[{"title":"' . $name . '"}]';
	} );
	$repository = new ProfileRepository( $directory, new CredentialCipher( $root . '/unused.key' ), null, [], $source );
	$identity = new Identity( ['USERNAME' => 'fixture'], static function () { throw new RuntimeException( 'Identity discovery not expected' ); } );
	checkJson( $paths === [], 'Profile and loader composition is lazy for JSON' );
	$snapshots[$name] = ( new Loader( $directory, $identity, $repository, $source ) )->load();
	checkJson( count( $paths ) === 5 && $snapshots[$name]['profile']['folders'][0]['title'] === $name && $snapshots[$name]['interface']['tooltips']['example'] === $name . ' tooltip', 'All sidecars and interface files use the supplied reader' );
}
checkJson( $snapshots['one']['profile']['dock'][0]['title'] === 'one', 'Second load does not contaminate the first snapshot' );
checkJson( ! file_exists( $root . '/unused.key' ), 'JSON reads never create a credential key' );
$input = new SettingsInput();
$normal = $input->normalise( ['folders_json' => $raw, 'link_templates_json' => '{}', 'dock_json' => ''] );
checkJson( $normal['profile']['folders']['number'] === 1 && $normal['profile']['linkTemplates'] === [] && $normal['profile']['dock'] === [], 'Form normalization preserves canonical decode round trip' );
failsJson( static function () use ( $input ) { $input->normalise( ['folders_json' => '[1]', 'dock_json' => '{'] ); }, JsonException::class );
$invalidSave = ['profile' => ['folders' => [], 'linkTemplates' => [], 'dock' => ["\xFF"]], 'settings' => ['DB_USER' => 'must-not-encrypt'], 'php' => []];
failsJson( static function () use ( $repository, $invalidSave ) { $repository->save( 'bad-save', $invalidSave ); }, JsonException::class );
checkJson( ! file_exists( $root . '/unused.key' ) && ! is_dir( $directory . '/profiles/bad-save' ), 'Serialization failure occurs before encryption or persistence' );

require __DIR__ . '/../config/helpers/json.php';
checkJson( validate_and_canonicalise_json( $raw ) === $expected && validate_and_canonicalise_json( '', false ) === '{}', 'Compatibility formatter delegates with the same signature' );
file_put_contents( $file, '[{"legacy":true}]' );
checkJson( read_json_array_safely( $file ) === [['legacy' => true]], 'Compatibility reader remains available' );
echo "PASS JSON services and configuration isolation\n";
