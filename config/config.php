<?php
/**
 * Application composition: the compatibility entry point for pages and utilities.
 * Existing profile files and constants remain supported during the migration.
 *
 * @var array<string, mixed> $config
 * @var \AMPBoard\Database\ConnectionFactory $database
 * @var \AMPBoard\Ui\Renderer $ui
 * @var \AMPBoard\Apache\CommandRunner $apacheCommands
 * @var \AMPBoard\Apache\Controller $apacheControl
 * @var \AMPBoard\Apache\VhostCatalog $vhosts
 * @var \AMPBoard\Php\InfoPage $phpInfo
 * @var \AMPBoard\Php\IniFile $phpIni
 * @var \AMPBoard\System\ServerInspector $serverInspector
 * @var \AMPBoard\System\Statistics $systemStatistics
 */

require_once __DIR__ . '/autoload.php';

if ( ! defined( 'AMPBOARD_NO_HELPERS' ) ) {
	require_once __DIR__ . '/helpers.php';
}

$cipher = new \AMPBoard\Security\CredentialCipher( defined( 'CRYPTO_KEY_FILE' ) ? CRYPTO_KEY_FILE : dirname( __DIR__ ) . '/.key' );
$profiles = new \AMPBoard\Config\ProfileRepository( __DIR__, $cipher, null, \AMPBoard\Config\LegacyConstants::read() );
$config = ( new \AMPBoard\Config\Loader( __DIR__, $profiles ) )->load( true );
$database = new \AMPBoard\Database\ConnectionFactory( $config['db'] );
$ui = new \AMPBoard\Ui\Renderer( $config );

$apacheCommands = new \AMPBoard\Apache\ShellCommandRunner();
$apacheControl = new \AMPBoard\Apache\Controller( $config['paths']['apache'], PHP_OS_FAMILY, $apacheCommands );
$vhosts = new \AMPBoard\Apache\VhostCatalog( $config['paths']['apache'], [
	getenv( 'WINDIR' ) ? getenv( 'WINDIR' ) . '/System32/drivers/etc/hosts' : '',
	'/etc/hosts',
] );

$exportFolders = new \AMPBoard\Export\FolderCatalog( $config['paths']['htdocs'], $config['profile']['folders'] );
$exportDatabase = new \AMPBoard\Export\DatabaseExporter( static function ( ?string $name ) use ( $database ) {
	return $database->connect( [ 'db' => $name ] );
} );
$exportSearchPaths = explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) );
if ( PHP_OS_FAMILY === 'Windows' ) {
	$exportSearchPaths[] = 'C:/Program Files/7-Zip';
	$exportSearchPaths[] = 'C:/Program Files (x86)/7-Zip';
}
$exports = new \AMPBoard\Export\Workflow( $exportFolders, $exportDatabase, new \AMPBoard\Export\ArchiveWriter(),
	new \AMPBoard\Export\ExternalArchiver( new \AMPBoard\Export\NativeProcessRunner(), $exportSearchPaths ),
	$config['export']['excludes'], dirname( __DIR__ ) . '/dist/exports', 'dist/exports', sys_get_temp_dir() );

$phpInfo = new \AMPBoard\Php\InfoPage();
$phpIni = new \AMPBoard\Php\IniFile( $config['php']['runtime']['loadedIni'] );

$serverInspector = new \AMPBoard\System\ServerInspector(
	new \AMPBoard\Apache\VersionProbe( $config['paths']['apache'], PHP_OS_FAMILY, $_SERVER['SERVER_SOFTWARE'] ?? '', $apacheCommands ),
	static function () use ( $database ) { return $database->connect( [ 'strictMode' => false ] ); },
	$config['php']['runtime']
);
$systemStatistics = new \AMPBoard\System\Statistics( PHP_OS_FAMILY, '/', $apacheCommands, [ new \AMPBoard\System\NativeMetrics(), 'read' ] );
