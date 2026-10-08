<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/helpers/security.php';
require __DIR__ . '/fixtures/export-database.php';
$root = $argv[1]; $scenario = $argv[2];
mkdir( $root . '/utils', 0700, true ); mkdir( $root . '/config' ); mkdir( $root . '/sites/site', 0700, true );
file_put_contents( $root . '/sites/site/file.txt', 'fixture only' );
copy( __DIR__ . '/../utils/export_files.php', $root . '/utils/export_files.php' );
file_put_contents( $root . '/config/config.php', '<?php /* isolated composition */' );
$runner = new class implements \AMPBoard\Export\ProcessRunner {
	public function run( array $arguments, string $directory, string $input = '' ): array { throw new RuntimeException( 'No external commands in request fixtures.' ); }
};
$exports = new \AMPBoard\Export\Workflow(
	new \AMPBoard\Export\FolderCatalog( $root, [ [ 'title' => 'Sites', 'dir' => 'sites' ] ] ),
	new \AMPBoard\Export\DatabaseExporter( static function ( $name ) { return new ExportConnection(); } ),
	new \AMPBoard\Export\ArchiveWriter(), new \AMPBoard\Export\ExternalArchiver( $runner, [] ), [], $root . '/public', 'dist/exports', $root );
$ui = new class { public function buildPageViewClasses( $view ) { return ''; } };
$config = [ 'user' => [ 'isDemo' => $scenario === 'demo' || $scenario === 'dbs-demo' ] ];
$demoMask = new \AMPBoard\Ui\DemoMask( $config['user']['isDemo'] );
define( 'DEMO_MODE', ! $config['user']['isDemo'] );
session_save_path( $root ); session_start(); $_SESSION['csrf_token'] = 'fixture';
$session = new \AMPBoard\Http\NativeSession();
$csrfTokens = new \AMPBoard\Security\CsrfToken( $session );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = [];
$_POST = [ 'action' => $scenario, 'csrf' => 'fixture', 'group' => '0', 'folder' => 'site', 'db' => 'fixture' ];
if ( in_array( $scenario, [ 'demo', 'session', 'csrf', 'input', 'get' ], true ) ) { $_POST['action'] = 'zip'; }
if ( $scenario === 'dbs-demo' ) { $_POST['action'] = 'dbs'; }
if ( $scenario === 'csrf' ) { $_POST['csrf'] = [ 'invalid' ]; }
if ( $scenario === 'input' ) { $_POST['folder'] = [ 'invalid' ]; }
if ( $scenario === 'get' ) { $_SERVER['REQUEST_METHOD'] = 'GET'; }
if ( $scenario === 'session' ) { session_write_close(); session_save_path( $root . '/missing' ); }
ob_start();
register_shutdown_function( static function () use ( $root, $scenario ): void {
	$body = ob_get_clean(); $data = json_decode( $body, true );
	$success = in_array( $scenario, [ 'scan', 'dbs', 'dbs-demo', 'token', 'zip', 'dumpdb' ], true );
	$ok = is_array( $data ) && $data['ok'] === $success;
	$rotated = in_array( $scenario, [ 'zip', 'dumpdb', 'input', 'get' ], true );
	$ok = $ok && ( $scenario === 'session' ? session_status() !== PHP_SESSION_ACTIVE : ( ( $_SESSION['csrf_token'] ?? '' ) !== 'fixture' ) === $rotated );
	if ( $success && in_array( $scenario, [ 'zip', 'dumpdb' ], true ) ) {
		$ok = $ok && $data['href'] === 'dist/exports/' . $data['name'] && is_file( $root . '/public/' . $data['name'] );
	}
	if ( $scenario === 'scan' ) { $ok = $ok && $data['groups'][0]['subfolders'][0]['name'] === 'site'; }
	if ( $scenario === 'dbs' ) { $ok = $ok && $data['databases'] === [ 'site2', 'site10' ]; }
	if ( $scenario === 'dbs-demo' ) { $ok = $ok && $data['databases'] === [ '************', '************' ]; }
	if ( $scenario === 'token' ) { $ok = $ok && is_string( $data['token'] ); }
	if ( ! $success ) { $ok = $ok && ! is_dir( $root . '/public' ) && isset( $data['error'] ); }
	$ok = $ok && glob( $root . '/ampboard-export-*' ) === [];
	if ( session_status() === PHP_SESSION_ACTIVE ) { session_destroy(); }
	echo ( $ok ? 'PASS' : 'FAIL ' . $body ) . ' export request ' . $scenario . "\n";
	exit( $ok ? 0 : 1 );
} );
require $root . '/utils/export_files.php';
