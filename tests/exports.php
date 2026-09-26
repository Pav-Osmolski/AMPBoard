<?php
/** Temporary fixture exports only; no live databases or project directories are exported. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/fixtures/export-database.php';
use AMPBoard\Export\ArchiveWriter;
use AMPBoard\Export\DatabaseExporter;
use AMPBoard\Export\ExternalArchiver;
use AMPBoard\Export\FileSelection;
use AMPBoard\Export\FolderCatalog;
use AMPBoard\Export\NativeProcessRunner;
use AMPBoard\Export\ProcessRunner;
use AMPBoard\Export\Workflow;

function checkExport( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function exportFailure( callable $action ): void {
	try { $action(); } catch ( Throwable $error ) { return; }
	throw new RuntimeException( 'Expected failure' );
}
function archiveFiles( string $path ): array {
	$out = [];
	if ( str_ends_with( $path, '.zip' ) ) {
		$zip = new ZipArchive(); checkExport( $zip->open( $path ) === true, 'Read ZIP' );
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( ! str_ends_with( $name, '/' ) ) { $out[$name] = $zip->getFromIndex( $i ); }
		}
		$zip->close();
	} else {
		$archive = new PharData( $path );
		$prefix = 'phar://' . str_replace( '\\', '/', realpath( $path ) ) . '/';
		foreach ( new RecursiveIteratorIterator( $archive ) as $file ) {
			$out[substr( str_replace( '\\', '/', $file->getPathname() ), strlen( $prefix ) )] = $file->getContent();
		}
	}
	ksort( $out ); return $out;
}
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } return false;
} );
checkExport( class_exists( ZipArchive::class ) && class_exists( PharData::class ), 'Enable ZIP and Phar for export integration tests.' );
$root = sys_get_temp_dir() . '/ampboard-export-test-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/htdocs/sites/site', 0700, true );
mkdir( $root . '/work' ); mkdir( $root . '/public' ); mkdir( $root . '/tools' );
register_shutdown_function( static function () use ( $root ): void {
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) { $item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); } rmdir( $root );
} );
$site = $root . '/htdocs/sites/site';
foreach ( [ 'index.php' => 'index', 'wp-config.php' => 'config', 'wp-content/uploads/photo.jpg' => 'photo',
	'wp-content/uploads-cache/keep.txt' => 'keep', '.git/secret' => 'omit', 'deep/VENDOR/omit' => 'omit', "space & quote's.txt" => 'special', 'unicode-é.txt' => 'unicode' ] as $file => $content ) {
	$path = $site . '/' . $file; if ( ! is_dir( dirname( $path ) ) ) { mkdir( dirname( $path ), 0700, true ); } file_put_contents( $path, $content );
}
mkdir( $site . '/empty' ); mkdir( $root . '/htdocs/sites/hidden' ); mkdir( $root . '/htdocs/sites/site10' );
$groups = [ [ 'title' => 'Sites', 'dir' => 'sites', 'excludeList' => [ 'hidden' ], 'urlRules' => [ 'match' => '/^site/' ] ] ];
$folders = new FolderCatalog( $root . '/htdocs', $groups );
checkExport( array_column( $folders->scan()[0]['subfolders'], 'name' ) === [ 'site', 'site10' ], 'Filtered natural-order folder list' );
foreach ( [ '../site', 'hidden', 'missing' ] as $invalid ) { exportFailure( static function () use ( $folders, $invalid ) { $folders->select( 0, $invalid ); } ); }
$connection = new ExportConnection();
$database = new DatabaseExporter( static function ( $name ) use ( &$connection ) { return $connection; } );
checkExport( $database->databases() === [ 'site2', 'site10' ] && $connection->closed, 'Database list filtering and connection cleanup' );
$connection = new ExportConnection(); $sql = $database->dump( 'fixture' );
checkExport( $connection->closed && substr_count( $sql, 'INSERT INTO `odd``table`' ) === 2 && str_contains( $sql, "'quote\\'\\\\value',NULL" ), 'Dump escaping, NULL values, identifier quoting and batch boundary' );
$connection = new ExportConnection(); $connection->fail = 'SELECT';
exportFailure( static function () use ( $database ) { $database->dump( 'fixture' ); } );
checkExport( $connection->closed, 'Failed query closes connection and refuses partial dump' );
$connection = new ExportConnection();
$runner = new class implements ProcessRunner {
	public array $calls = [];
	public function run( array $arguments, string $directory, string $input = '' ): array {
		$this->calls[] = [ $arguments, $directory ];
		$destination = $arguments[5]; file_put_contents( $destination, 'partial archive' );
		return [ 'success' => false, 'output' => 'failed' ];
	}
};
$tool = $root . '/tools/' . ( PHP_OS_FAMILY === 'Windows' ? '7z.exe' : '7z' ); file_put_contents( $tool, 'fixture' ); chmod( $tool, 0700 );
$external = new ExternalArchiver( $runner, [ $root . '/tools' ] );
function workflowFor( bool $zip, string $destination = '' ): Workflow {
	global $folders, $database, $external, $root;
	return new Workflow( $folders, $database, new ArchiveWriter( $zip ), $external, [ '.git', 'vendor' ], $destination ?: $root . '/public', 'dist/exports', $root . '/work' );
}
$expected = [ 'index.php' => 'index', 'wp-config.php' => 'config', 'wp-content/uploads-cache/keep.txt' => 'keep', "space & quote's.txt" => 'special', 'unicode-é.txt' => 'unicode' ]; ksort( $expected );
foreach ( [ true, false ] as $zip ) {
	foreach ( [ 'exclude', 'include', 'only' ] as $mode ) {
		$result = workflowFor( $zip )->files( 0, 'site', $mode, 'php' );
		$wanted = $mode === 'only' ? [ 'photo.jpg' => 'photo' ] : $expected;
		if ( $mode === 'include' ) { $wanted['wp-content/uploads/photo.jpg'] = 'photo'; } ksort( $wanted );
		checkExport( archiveFiles( $root . '/public/' . $result['name'] ) === $wanted, 'Archive contents for ' . $mode . ( $zip ? ' ZIP' : ' TAR' ) );
		checkExport( $result['href'] === 'dist/exports/' . $result['name'] && $result['message'] === null, 'Download response' );
	}
}
$cwd = getcwd(); $result = workflowFor( true )->files( 0, 'site', 'exclude', 'external' );
checkExport( count( $runner->calls ) === 1 && getcwd() === $cwd && $result['message'] !== null, 'Failed external command falls back without changing process cwd' );
checkExport( archiveFiles( $root . '/public/' . $result['name'] ) === $expected, 'Partial external output never leaks into fallback archive' );
foreach ( [ true, false ] as $zip ) {
	$result = workflowFor( $zip )->dump( 'fixture', 'external' );
	$contents = archiveFiles( $root . '/public/' . $result['name'] );
	checkExport( count( $contents ) === 1 && str_ends_with( array_key_first( $contents ), '.sql' ) && str_contains( reset( $contents ), 'CREATE TABLE' ), 'SQL dump packaged as a single file' );
}
file_put_contents( $root . '/blocked', 'not a directory' );
exportFailure( static function () use ( $root ) { workflowFor( true, $root . '/blocked' )->dump( 'fixture', 'php' ); } );
checkExport( glob( $root . '/work/*' ) === [] && glob( $root . '/public/*.part' ) === [], 'Private SQL, manifests and partial archives cleaned on success/failure' );
$before = glob( $root . '/public/*' );
$connection = new ExportConnection(); $connection->fail = 'SELECT';
exportFailure( static function () { workflowFor( true )->dump( 'fixture', 'php' ); } );
checkExport( $connection->closed && glob( $root . '/work/*' ) === [] && glob( $root . '/public/*' ) === $before, 'Failed database dump publishes nothing and removes its workspace' );
$connection = new ExportConnection();
// Real external tools, when installed, see only fixture files selected by the same policy.
$search = array_merge( explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) ), [ 'C:/Program Files/7-Zip', '/usr/bin' ] );
$real = new ExternalArchiver( new NativeProcessRunner(), $search );
$selection = ( new FileSelection() )->select( $site, [ '.git', 'vendor' ], 'exclude' );
try { $path = $real->create( $selection['entries'], $selection['source'], $root . '/real.zip' ); }
catch ( RuntimeException $error ) {
	if ( $error->getMessage() !== 'No external archiver (7-Zip or zip) found on PATH.' ) { throw $error; }
	$path = null; echo "SKIP real external archiver: not installed\n";
}
if ( $path !== null ) {
	checkExport( archiveFiles( $path ) === $expected, 'Real external archive matches PHP file contents' );
	foreach ( [ 'include', 'only' ] as $mode ) {
		$selected = ( new FileSelection() )->select( $site, [ '.git', 'vendor' ], $mode );
		$path = $real->create( $selected['entries'], $selected['source'], $root . '/real-' . $mode . '.zip' );
		$wanted = $mode === 'only' ? [ 'photo.jpg' => 'photo' ] : $expected + [ 'wp-content/uploads/photo.jpg' => 'photo' ]; ksort( $wanted );
		checkExport( archiveFiles( $path ) === $wanted, 'Real external uploads mode ' . $mode );
	}
	echo "PASS real external archive\n";
}
echo "PASS export services\n";

$tar = ( new ArchiveWriter( false, false ) )->create( $selection['entries'], $root . '/plain.zip' );
checkExport( str_ends_with( $tar, '.tar' ) && archiveFiles( $tar ) === $expected, 'Uncompressed TAR fallback' );
foreach ( [ 'scan', 'dbs', 'token', 'zip', 'dumpdb', 'demo', 'csrf', 'input', 'get', 'unknown' ] as $scenario ) {
	$args = [ PHP_BINARY, '-n', '-d', 'phar.readonly=0' ];
	// Phar is built in on Windows but packaged as a shared extension on CI Linux.
	$phar = rtrim( ini_get( 'extension_dir' ), '/\\' ) . '/' . ( PHP_OS_FAMILY === 'Windows' ? 'php_phar.dll' : 'phar.so' );
	if ( is_file( $phar ) ) { array_push( $args, '-d', 'extension=' . $phar ); }
	array_push( $args, __DIR__ . '/export-request.php', $root . '/request-' . $scenario, $scenario );
	$process = proc_open( $args,
		[ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
	$errors = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
	checkExport( proc_close( $process ) === 0 && str_starts_with( $output, 'PASS' ), $output . $errors );
	echo $output;
}
