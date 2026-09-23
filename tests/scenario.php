<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/fixtures/database.php';
require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/helpers.php';

use AMPBoard\Config\Loader;
use AMPBoard\Database\ConnectionFactory;
use AMPBoard\Ui\Renderer;
use AMPBoard\Ui\ThemeCatalog;

function expect( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );

$scenario = $argv[1] ?? '';
$root = dirname( __DIR__ );
$credentials = [ 'host' => 'fixture-host', 'user' => 'fixture-user', 'pass' => 'fixture-pass' ];
$database = new ConnectionFactory( $credentials );

if ( $scenario === 'database' ) {
	foreach ( [ false, true ] as $strict ) {
		foreach ( [ 0, 1, 3 ] as $mode ) {
			mysqli_driver::$mode = $mode;
			$conn = $database->connect( [ 'strictMode' => $strict, 'db' => 'selected' ] );
			expect( mysqli_driver::$mode === $mode, 'Success restores reporting mode' );
			expect( $conn->charset === 'utf8mb4' && $conn->database === 'selected', 'Connection setup' );
			mysqli::$error = 'Connection refused';
			try { $conn = $database->connect( [ 'strictMode' => $strict ] ); expect( ! $strict, 'Strict failure throws' ); }
			catch ( Exception $e ) { expect( $strict && str_contains( $e->getMessage(), 'MySQL connection failed' ), 'Exception translation' ); }
			expect( mysqli_driver::$mode === $mode, 'Failure restores reporting mode' );
			mysqli::$error = '';
		}
	}
	expect( mysqli::$connections[0] === array_values( $credentials ), 'Uses injected credentials' );
	$database->connect( [ 'user' => 'override', 'pass' => '' ] );
	expect( end( mysqli::$connections ) === [ 'fixture-host', 'override', '' ], 'Empty password override' );
	mysqli::$failCharset = true;
	try { $database->connect(); throw new RuntimeException( 'Expected charset failure' ); }
	catch ( Exception $e ) { expect( str_contains( $e->getMessage(), 'charset failed' ), 'Charset failure propagated' ); }
	expect( mysqli_driver::$mode === 3, 'Charset failure restores mode' );
	mysqli::$failCharset = false;
	expect( $database->credentialStatus() === [ 'host' => true, 'user' => true, 'pass' => true ], 'Successful status' );
	mysqli::$error = 'Access denied using password: YES';
	expect( $database->credentialStatus() === [ 'host' => true, 'user' => true, 'pass' => false ], 'Credential status heuristic preserved' );
	expect( $database->credentialStatus( 'unknown' ) === false, 'Unknown status key' );
	exit( "PASS database\n" );
}

if ( $scenario === 'renderer' ) {
	$config = [ 'paths' => [ 'assets' => $root . '/assets' ], 'ui' => [ 'flags' => [ 'folderBadges' => false ] ],
		'interface' => [ 'headings' => [ 'Example' => [ 'key' => 'example' ] ], 'tooltips' => [ 'example' => '<safe & useful>' ] ] ];
	$ui = new Renderer( $config, $database );
	$config['ui']['flags']['folderBadges'] = true;
	$other = new Renderer( $config, $database );
	expect( $ui->renderBadge( 'test' ) === '' && $other->renderBadge( 'test' ) !== '', 'Renderer instances are isolated' );
	expect( str_contains( $ui->renderHeading( 'Example' ), '&lt;safe &amp; useful&gt;' ), 'Configured tooltip escaped' );
	expect( str_contains( $ui->renderHeading( 'Unknown' ), 'Missing tooltip key: unknown' ), 'Tooltip fallback' );
	unset( $GLOBALS['config'] );
	expect( str_contains( $ui->renderHeading( 'Example' ), 'tooltip-example' ), 'No global config dependency' );
	expect( str_contains( $ui->renderCollapseToggle( 'header' ), 'aria-expanded="true"' ), 'Collapse semantics' );
	expect( str_contains( $ui->renderDragHandle( 'A & B' ), 'Reorder A &amp; B' ), 'Drag semantics and escaping' );
	ob_start(); $ui->renderAccordionSectionStart( 'sample', 'Heading' ); $ui->renderAccordionSectionEnd(); $html = ob_get_clean();
	expect( str_contains( $html, 'aria-controls="panel-sample"' ), 'Accordion semantics' );
	expect( ! isset( $GLOBALS['settingsView'] ) && ! defined( 'SETTINGS_VIEW' ), 'Renderer does not mutate view context' );
	expect( $ui->buildPageViewClasses( true ) === '' && $ui->buildPageViewClasses( null ) === 'page-view', 'Standalone/embedded classes' );
	$_SERVER['SCRIPT_NAME'] = '/amp/utils/export_files.php';
	$assets = $ui->renderVersionedAssetsWithBase();
	expect( str_contains( $assets, '/amp/dist/css/style.min.css?v=' . filemtime( $root . '/dist/css/style.min.css' ) ), 'Asset base and version' );
	$themes = new ThemeCatalog( $root . '/assets' );
	expect( $themes->getThemeColorScheme( 'missing' ) === 'dark', 'Missing theme fallback' );
	[ $options, $types ] = $themes->loadThemes( $root . '/assets/scss/themes/' );
	expect( isset( $options['synthwave'] ) && count( $types ) > 0, 'Theme metadata' );
	foreach ( $types as $name => $type ) { expect( $themes->getThemeColorScheme( $name ) === $type, 'Theme scheme: ' . $name ); }
	expect( str_contains( $themes->buildBodyClasses( 'default', false, true, true, true, true, true, true, true, false, false, false ), 'system-monitor-inactive' ), 'Unavailable panels stay inactive' );
	exit( "PASS renderer\n" );
}

// Each profile scenario has its own process because legacy PHP profiles define constants.
$temp = sys_get_temp_dir() . '/ampboard-tests-' . bin2hex( random_bytes( 6 ) );
mkdir( $temp . '/config/profiles/default', 0777, true );
mkdir( $temp . '/config/interface', 0777, true );
register_shutdown_function( static function () use ( $temp ): void {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $files as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
	rmdir( $temp );
} );
$_SERVER['USERNAME'] = 'fixture-user';
$_SERVER['USER'] = 'fixture-user';
$_SERVER['REQUEST_METHOD'] = 'GET';
$profile = '<?php $theme = "overcast"; $displayHeader = false;';
file_put_contents( $temp . '/config/profiles/default/user_config.php', $profile );
file_put_contents( $temp . '/config/profiles/default/folders.json', '[{"label":"default"}]' );
file_put_contents( $temp . '/config/interface/tooltips.json', '{"example":"Fixture tooltip"}' );
if ( $scenario === 'config-user' ) {
	mkdir( $temp . '/config/profiles/fixture-user' );
	file_put_contents( $temp . '/config/profiles/fixture-user/user_config.php', '<?php $theme = "dracula"; $displayHeader = true;' );
	file_put_contents( $temp . '/config/profiles/fixture-user/user_config.php', ' ini_set("memory_limit", "256M");', FILE_APPEND );
	file_put_contents( $temp . '/config/profiles/fixture-user/folders.json', '[{"label":"user"}]' );
}
if ( $scenario === 'config-local' ) {
	file_put_contents( $temp . '/config/local.php', '<?php define("DB_HOST", "local-host"); define("DEMO_MODE", true);' );
}
$loaded = ( new Loader( $temp . '/config' ) )->load();
expect( count( mysqli::$connections ) === 1, 'One credential probe during initialization' );
expect( $loaded['ui']['tooltips']['map'] === [ 'example' => 'Fixture tooltip' ], 'Tooltips populated before use' );
expect( $loaded['ui']['flags']['footer'] === true, 'Missing values receive defaults' );
expect( $loaded['profile']['dock'] === [], 'Missing JSON safely defaults' );
expect( ! isset( $rawUser, $dbUser, $theme, $activeConfigDir ), 'Loader variables do not leak' );
if ( $scenario === 'config-user' ) {
	expect( $loaded['ui']['themes']['theme'] === 'dracula' && $loaded['ui']['flags']['header'] === true, 'User PHP profile selected' );
	expect( $loaded['profile']['folders'][0]['label'] === 'user', 'User JSON selected' );
	expect( $loaded['user']['phpMemoryLimit'] === '256M', 'Saved profile PHP settings still apply' );
} else {
	expect( $loaded['ui']['themes']['theme'] === 'overcast' && $loaded['ui']['flags']['header'] === false, 'Default PHP fallback and false preserved' );
	expect( $loaded['profile']['folders'][0]['label'] === 'default', 'Default JSON fallback' );
}
if ( $scenario === 'config-local' ) {
	expect( $loaded['db']['host'] === 'local-host' && $loaded['user']['name'] === 'demo', 'Local overrides and demo identity' );
}
echo "PASS $scenario\n";
