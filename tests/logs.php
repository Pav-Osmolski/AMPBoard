<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
use AMPBoard\Logs\Paths;
use AMPBoard\Logs\TailReader;
use AMPBoard\Logs\Viewer;
function checkLog( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } return false;
} );
$root = sys_get_temp_dir() . '/ampboard-log-test-' . bin2hex( random_bytes( 8 ) ); mkdir( $root, 0700 );
register_shutdown_function( static function () use ( $root ): void {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $files as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); } rmdir( $root );
} );
$reader = new TailReader(); $path = $root . '/error.log';
foreach ( [ '', 'one', "one\n", "one\n\n", "a\r\nb\r\n\r\nc", "a\n \n\nb\nc\n", str_repeat( "normal & <line>\n", 1500 ) . 'final' ] as $source ) {
	file_put_contents( $path, $source );
	foreach ( [ 1, 5, 25 ] as $lines ) {
		$expected = implode( '', array_slice( file( $path ), -$lines ) );
		checkLog( $reader->read( $path, $lines ) === $expected, 'Apache line endings/count' );
		$parts = array_values( array_filter( explode( "\n", $source ), static function ( $line ) { return $line !== ''; } ) );
		$expected = implode( "\n", array_filter( array_slice( $parts, -$lines ), static function ( $line ) { return trim( $line ) !== ''; } ) );
		checkLog( $reader->read( $path, $lines, true ) === $expected, 'PHP historical blank-line/count policy' );
	}
}
$stream = fopen( $path, 'wb' ); for ( $i = 0; $i < 2048; $i++ ) { fwrite( $stream, str_repeat( 'x', 4095 ) . "\n" ); } fwrite( $stream, "last1\nlast2\nlast3\nlast4\nlast5\n" ); fclose( $stream );
checkLog( $reader->read( $path, 5 ) === "last1\nlast2\nlast3\nlast4\nlast5\n", 'Large log reads only its tail' );
file_put_contents( $path, str_repeat( 'x', 2000 ) );
$limited = new TailReader( 1024 ); $excerpt = $limited->read( $path, 5 );
checkLog( str_starts_with( $excerpt, '[Log excerpt limited to the last 1024 bytes.]' ) && strlen( $excerpt ) < 1100, 'Giant line has bounded visible truncation' );
file_put_contents( $path, str_repeat( 'x', 2000 ) . "\ncomplete\n" );
checkLog( str_ends_with( $limited->read( $path, 5 ), "\ncomplete\n" ), 'Truncated partial leading line discarded' );
checkLog( $reader->read( $root . '/missing', 5 ) === null && $reader->read( $root, 5 ) === null, 'Missing file and directory unavailable' );
$failed = new class extends TailReader { protected function open( string $path ) { return false; } };
checkLog( ( new Viewer( [ $path ], $failed, 'PHP', 25, true ) )->content() === 'PHP error log could not be read.', 'Unreadable file handled without warnings' );
checkLog( ( new Viewer( [ $root, $root . '/missing' ], $reader, 'Apache', 5 ) )->content() === 'Apache error log not found or not configured.', 'Non-file candidates skipped' );
file_put_contents( $root . '/second', 'second' ); file_put_contents( $path, 'first' );
checkLog( ( new Viewer( [ $path, $root . '/second' ], $reader, 'Apache', 5 ) )->content() === 'first', 'Candidate order' );
checkLog( ( new Viewer( [ $root . '/missing', $root . '/second' ], $reader, 'Apache', 5 ) )->content() === 'second', 'Candidate fallback' );
foreach ( [ 'Windows', 'Darwin', 'Linux' ] as $os ) { checkLog( Paths::apache( '/fixture', $os, '/home/test' )[0] === '/fixture/logs/error.log', 'Configured root comes first on ' . $os ); }
$linux = Paths::apache( '/fixture', 'Linux', '/home/test' ); checkLog( end( $linux ) === '/home/test/snap/httpd/common/error.log', 'Injected home fallback' );
foreach ( [ 'apache', 'php' ] as $kind ) {
	foreach ( [ 'ajax', 'embedded', 'disabled', 'panel' ] as $mode ) {
		$process = proc_open( [ PHP_BINARY, '-n', __DIR__ . '/log-request.php', $root . '/' . $kind . '-' . $mode, $kind, $mode ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
		fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
		checkLog( proc_close( $process ) === 0 && str_starts_with( $out, 'PASS' ), $out . $err ); echo $out;
	}
}
echo "PASS log services\n";
