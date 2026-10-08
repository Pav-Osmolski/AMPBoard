<?php
/** Explicit exports dependencies; constructed once per request. */
require_once __DIR__ . "/application.php";
require_once __DIR__ . "/services-database.php";
require_once __DIR__ . "/services-request.php";
require_once __DIR__ . "/services-ui.php";

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
