<?php
/** All folder launches are supplied doubles; native checks run only benign PHP fixtures. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
use AMPBoard\Filesystem\FolderOpener;
use AMPBoard\System\ProcessLauncher;
use AMPBoard\System\NativeProcessLauncher;
use AMPBoard\Http\FolderOpenAction;

function checkFolderOpen( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
final class FixtureFolderLauncher implements ProcessLauncher {
	public array $calls = [];
	public bool $success = true;
	public bool $throws = false;
	public function launch( array $arguments ): bool {
		$this->calls[] = $arguments;
		if ( $this->throws ) { throw new Error( 'private launcher detail' ); }
		return $this->success;
	}
}
$calls = []; $launcher = new FixtureFolderLauncher();
$directory = static function ( string $path ) use ( &$calls ): bool { $calls[] = $path; return true; };
$linux = new FolderOpener( 'Linux', $launcher, $directory );
checkFolderOpen( $calls === [] && $launcher->calls === [], 'Construction has no discovery or launch side effects' );
$path = '/tmp/folder with spaces & ; $(whoami) `x` "quoted" é';
$linux->open( $path );
checkFolderOpen( $launcher->calls === [ [ 'xdg-open', $path ] ] && $calls === [ $path ], 'Unix path stays one data argument with punctuation and Unicode' );
$macLauncher = new FixtureFolderLauncher();
( new FolderOpener( 'Darwin', $macLauncher, $directory ) )->open( '/Applications/Folder With Spaces' );
checkFolderOpen( $macLauncher->calls === [ [ 'open', '/Applications/Folder With Spaces' ] ], 'Independent macOS launch policy' );
$winLauncher = new FixtureFolderLauncher();
$windows = new FolderOpener( 'Windows', $winLauncher, $directory );
$windowsPath = "C:/Stack & Tools/it's %TEMP% ! \$ ` é";
$windows->open( $windowsPath );
$args = $winLauncher->calls[0];
$script = str_replace( "\0", '', base64_decode( $args[4], true ) );
checkFolderOpen( array_slice( $args, 0, 4 ) === [ 'powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand' ], 'Windows invokes fixed launch utility without cmd.exe' );
checkFolderOpen( preg_match( "/FromBase64String\('([^']+)'\)/", $script, $match ) === 1
	&& base64_decode( $match[1], true ) === '"' . str_replace( '/', '\\', $windowsPath ) . '"', 'Windows path is encoded data, not executable source' );
checkFolderOpen( str_contains( $script, 'Start-Process' ) && ! str_contains( $script, '%TEMP%' ) && ! str_contains( $script, "it's" ), 'Launcher starts Explorer without interpolating path text' );
$windows->open( '\\\\server\\share\\folder with spaces' );
checkFolderOpen( count( $winLauncher->calls ) === 2, 'UNC directory paths supported' );
$windows->open( 'C:/' );
$rootScript = str_replace( "\0", '', base64_decode( $winLauncher->calls[2][4], true ) );
preg_match( "/FromBase64String\('([^']+)'\)/", $rootScript, $rootMatch );
checkFolderOpen( base64_decode( $rootMatch[1], true ) === '"C:\\\\"', 'Windows drive root keeps its trailing slash inside quoted argument' );
$windows->open( '\\\\server.example\\share\\' );
$windows->open( '\\\\127.0.0.1\\share\\folder' );
checkFolderOpen( count( $winLauncher->calls ) === 5, 'UNC hostnames and IP addresses supported' );

foreach ( [ 'Linux' => [ '', 'relative', '-option', 'file:///tmp', "C:\\root", "/tmp\nfolder", "/tmp\0folder" ],
	'Windows' => [ '', 'relative', 'C:relative', '\\root', '/root', '\\\\server', '\\\\?\\C:\\root', '\\\\.\\device', 'C:/bad"path', "C:/bad\npath" ] ] as $os => $invalid ) {
	$runner = new FixtureFolderLauncher(); $checked = 0;
	$opener = new FolderOpener( $os, $runner, static function () use ( &$checked ): bool { ++$checked; return true; } );
	foreach ( $invalid as $bad ) {
		try { $opener->open( $bad ); throw new RuntimeException( 'Invalid path accepted' ); }
		catch ( InvalidArgumentException $error ) { checkFolderOpen( $error->getMessage() === 'Invalid or missing folder path.', 'Stable path error' ); }
	}
	checkFolderOpen( $runner->calls === [] && $checked === 0, 'Invalid syntax rejected before filesystem and launcher for ' . $os );
}
$root = sys_get_temp_dir() . '/ampboard-folder-open-' . bin2hex( random_bytes( 8 ) ); mkdir( $root, 0700 );
mkdir( $root . '/directory with spaces' ); file_put_contents( $root . '/file.txt', 'fixture' );
try {
	$nativePath = PHP_OS_FAMILY === 'Windows' ? str_replace( '/', '\\', $root ) : $root;
	$actual = new FixtureFolderLauncher(); $opener = new FolderOpener( PHP_OS_FAMILY, $actual );
	$opener->open( $nativePath . '/directory with spaces' );
	foreach ( [ $nativePath . '/missing', $nativePath . '/file.txt' ] as $bad ) {
		try { $opener->open( $bad ); throw new RuntimeException( 'Non-directory accepted' ); }
		catch ( InvalidArgumentException $error ) {}
	}
	checkFolderOpen( count( $actual->calls ) === 1, 'Native directory check rejects regular files and missing targets' );
} finally { unlink( $root . '/file.txt' ); rmdir( $root . '/directory with spaces' ); rmdir( $root ); }

$actionLauncher = new FixtureFolderLauncher();
$action = new FolderOpenAction( new FolderOpener( 'Linux', $actionLauncher, static function () { return true; } ) );
checkFolderOpen( $action->handle( 'GET', '{broken' ) === [ 'status' => 405, 'body' => [ 'error' => 'Invalid request method.' ] ], 'Method guard runs before body processing' );
foreach ( [ '{broken', 'null', 'true', '"text"', '[]', '{}', '{"path":null}', '{"path":[]}', '{"path":123}', '{"path":"relative"}' ] as $body ) {
	checkFolderOpen( $action->handle( 'POST', $body ) === [ 'status' => 400, 'body' => [ 'error' => 'Invalid or missing folder path.' ] ], 'Invalid request: ' . $body );
}
checkFolderOpen( $actionLauncher->calls === [], 'Rejected requests never launch' );
checkFolderOpen( $action->handle( 'POST', json_encode( [ 'path' => $path ] ) ) === [ 'status' => 200, 'body' => [ 'success' => true, 'message' => 'Opened: ' . $path ] ], 'Success response preserves supplied path' );
$actionLauncher->success = false;
checkFolderOpen( $action->handle( 'POST', '{"path":"/tmp"}' ) === [ 'status' => 500, 'body' => [ 'error' => 'Failed to open folder.' ] ], 'Failed launch cannot report success' );
$actionLauncher->throws = true;
checkFolderOpen( $action->handle( 'POST', '{"path":"/tmp"}' )['body'] === [ 'error' => 'Failed to open folder.' ], 'Launcher exceptions do not expose private details' );
$unavailable = new FolderOpenAction( new FolderOpener( 'Unknown', $actionLauncher, $directory ) );
checkFolderOpen( $unavailable->handle( 'POST', '{"path":"/tmp"}' ) === [ 'status' => 500, 'body' => [ 'error' => 'Unsupported OS: Unknown' ] ], 'Unsupported platform response' );
$failedCheck = new FolderOpenAction( new FolderOpener( 'Linux', $actionLauncher, static function () { throw new RuntimeException( 'private path detail' ); } ) );
checkFolderOpen( $failedCheck->handle( 'POST', '{"path":"/tmp"}' )['body'] === [ 'error' => 'Failed to open folder.' ], 'Filesystem exceptions do not expose private details' );

$native = new NativeProcessLauncher();
if ( PHP_OS_FAMILY === 'Windows' ) {
	// Shadow the cmdlet before evaluating the production wrapper: this cannot start Explorer.
	$fakeStart = 'function Start-Process { param([string]$FilePath, [string]$ArgumentList) $expected = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String(\''
		. $match[1] . '\')); if ($FilePath -ne \'explorer.exe\' -or $ArgumentList -cne $expected) { throw \'Argument mismatch\' } }; ';
	$testScript = $fakeStart . $script;
	$testArgs = $args; $testArgs[4] = base64_encode( implode( "\0", str_split( $testScript ) ) . "\0" );
	checkFolderOpen( $native->launch( $testArgs ), 'Native Windows wrapper accepts encoded path data with a simulated Start-Process' );
}
ob_start();
$success = $native->launch( [ PHP_BINARY, '-n', __DIR__ . '/folder-launcher.php', 'arguments', 'a & b %HOME% ! $ ` " quote', 'Unicode é directory' ] );
$failure = $native->launch( [ PHP_BINARY, '-n', __DIR__ . '/folder-launcher.php', 'failure' ] );
$missing = $native->launch( [ __DIR__ . '/missing-launcher-executable' ] );
$output = ob_get_clean();
checkFolderOpen( $success && ! $failure && ! $missing && $output === '', 'Native argument separation, exit status and captured output' );
checkFolderOpen( ! $native->launch( [] ) && ! $native->launch( [ '' ] ) && ! $native->launch( [ PHP_BINARY, [] ] ) && ! $native->launch( [ "bad\0path" ] ), 'Malformed launcher arguments rejected' );
foreach ( [ 'proc_open', 'proc_close' ] as $disabled ) {
	passthru( escapeshellarg( PHP_BINARY ) . ' -n -d ' . escapeshellarg( 'disable_functions=' . $disabled ) . ' ' . escapeshellarg( __DIR__ . '/folder-launcher.php' ), $code );
	checkFolderOpen( $code === 0, 'Disabled process operation: ' . $disabled );
}
echo "PASS folder opening, request policy and native launch boundary\n";
foreach ( [ 'GET', 'POST' ] as $method ) {
	passthru( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __DIR__ . '/folder-open-request.php' ) . ' ' . $method, $code );
	checkFolderOpen( $code === 0, 'Actual endpoint guard: ' . $method );
}
