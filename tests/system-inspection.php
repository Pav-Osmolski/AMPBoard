<?php
/** Fixture probes only: no live database connections or external commands. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
use AMPBoard\Apache\CommandRunner;
use AMPBoard\Apache\VersionProbe;
use AMPBoard\System\ServerInspector;
use AMPBoard\System\Statistics;
use AMPBoard\Ui\Renderer;
use AMPBoard\Database\ServerVersion;

function checkSystem( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
class FixtureCommands implements CommandRunner {
	public array $calls = [];
	public string $output = '';
	public bool $success = true;
	public bool $throws = false;
	public array $responses = [];
	public function run( string $command ): array {
		$this->calls[] = $command;
		if ( $this->throws ) { throw new RuntimeException( 'Probe failure' ); }
		return [ 'success' => $this->success, 'output' => $this->responses[$command] ?? $this->output ];
	}
}
$root = sys_get_temp_dir() . '/ampboard-system-' . bin2hex( random_bytes( 8 ) ); mkdir( $root . '/apache space/bin', 0700, true );
$binary = $root . '/apache space/bin/httpd'; file_put_contents( $binary, 'fixture only' );
register_shutdown_function( static function () use ( $root, $binary ): void {
	unlink( $binary ); rmdir( dirname( $binary ) ); rmdir( dirname( dirname( $binary ) ) );
	foreach ( glob( $root . '/request-*' ) as $dir ) { unlink( $dir . '/utils/system_stats.php' ); unlink( $dir . '/config/config.php' ); rmdir( $dir . '/utils' ); rmdir( $dir . '/config' ); rmdir( $dir ); }
	rmdir( $root );
} );
$commands = new FixtureCommands(); $commands->output = 'Server version: Apache/2.4.62 (Fixture)';
$apache = new VersionProbe( $root . '/apache space', PHP_OS_FAMILY, '', $commands );
$connection = new class {
	public string $connect_error = '';
	public string $server_info = '10.11.7-MariaDB-log';
	public bool $closed = false;
	public function close(): void { $this->closed = true; }
};
$connections = 0;
$runtime = [ 'version' => '8.3.1', 'threadSafe' => true, 'sapi' => 'cgi-fcgi' ];
$inspector = new ServerInspector( $apache, static function () use ( &$connections, $connection ) { ++$connections; return $connection; }, $runtime );
checkSystem( $commands->calls === [] && $connections === 0, 'Construction is lazy' );
$info = $inspector->inspect();
checkSystem( $info['apache'] === [ 'status' => 'available', 'version' => '2.4.62' ] && $commands->calls === [ escapeshellarg( $binary ) . ' -v' ], 'Quoted Apache binary and parsed version' );
checkSystem( $connection->closed && $info['database'] === [ 'available' => true, 'label' => '10.11.7-MariaDB' ], 'Database result normalized and connection closed' );
$ui = new Renderer( [] ); $count = count( $commands->calls );
ob_start(); $ui->renderServerInfo( $info ); $html = ob_get_clean();
checkSystem( str_contains( $html, '2.4.62' ) && str_contains( $html, '8.3.1 TS FastCGI' ) && str_contains( $html, '10.11.7-MariaDB' ), 'Header content and labels' );
checkSystem( count( $commands->calls ) === $count && $connections === 1, 'Rendering performs no probes' );
$discovery = new FixtureCommands();
$discovery->responses = [ 'where httpd' => $root . "/absent\r\n" . $binary,
	'which httpd' => $binary, 'command -v apachectl 2>/dev/null' => $binary,
	escapeshellarg( $binary ) . ' -v' => 'Server version: Apache/2.4.62' ];
foreach ( [ 'Windows', 'Darwin', 'Linux' ] as $os ) {
	checkSystem( ( new VersionProbe( $root . '/missing', $os, '', $discovery ) )->inspect()['version'] === '2.4.62', 'Platform fallback discovery ' . $os );
}
$unsafe = $info; $unsafe['database']['label'] = '<custom & version>'; $unsafe['php']['version'] = '<php>'; $unsafe['apache']['version'] = '<apache>';
ob_start(); $ui->renderServerInfo( $unsafe ); $html = ob_get_clean();
checkSystem( str_contains( $html, '&lt;custom &amp; version&gt;' ) && str_contains( $html, '&lt;php&gt;' ) && str_contains( $html, '&lt;apache&gt;' ), 'Success labels escaped at rendering boundary' );
$connection->connect_error = '<refused & unavailable>'; $connection->closed = false;
$commands->success = false;
$info = $inspector->inspect();
checkSystem( ! $info['database']['available'] && $connection->closed && $info['apache']['status'] === 'unavailable', 'Failed status closes connection and ignores unsuccessful command output' );
ob_start(); $ui->renderServerInfo( $info ); $html = ob_get_clean();
checkSystem( str_contains( $html, '&lt;refused &amp; unavailable&gt;' ) && str_contains( $html, 'Not detected' ), 'Failure HTML escaped' );
$commands->throws = true;
checkSystem( ( new VersionProbe( $root, 'Windows', 'Fixture Apache', $commands ) )->inspect()['status'] === 'unknown', 'Failed command with server fallback' );
$failed = new ServerInspector( $apache, static function () { throw new Error( 'Driver unavailable' ); }, $runtime );
checkSystem( $failed->inspect()['database']['label'] === 'Driver unavailable', 'Unavailable driver becomes display result' );
foreach ( [ '8.0.36-0.el9' => '8.0.36', '8.0.36-28' => '8.0.36-Percona', '5.7.mysql_aurora.2.11.1' => 'Aurora MySQL 5.7 (2.11.1)', '8.0.33-ndb-8.0.33' => '8.0.33-ndb' ] as $raw => $expected ) { checkSystem( ServerVersion::normalise( $raw ) === $expected, 'Version label ' . $raw ); }
$commands->throws = false; $commands->success = true; $commands->output = '4';
$metrics = [ 'load' => [ 2 ], 'peakMemory' => 4 * 1024 * 1024, 'diskFree' => 25, 'diskTotal' => 100 ];
$paths = [];
$read = static function ( string $path ) use ( &$metrics, &$paths ): array { $paths[] = $path; return $metrics; };
foreach ( [ 'Linux', 'Darwin' ] as $os ) {
	$stats = new Statistics( $os, '/fixture', $commands, $read );
	checkSystem( $stats->snapshot() === [ 'cpu' => 50.0, 'memory' => 4.0, 'disk' => 25.0 ], 'Statistics units for ' . $os );
}
checkSystem( $paths === [ '/fixture', '/fixture' ], 'Disk target explicitly supplied' );
$commands->success = false;
checkSystem( $stats->snapshot()['cpu'] === 2.0, 'Raw Unix load fallback retained' );
$commands->success = true; $commands->output = '"timestamp","12.6"';
$windows = new Statistics( 'Windows', '/', $commands, $read );
checkSystem( $windows->snapshot()['cpu'] === 13.0, 'Windows typeperf parsing' );
$commands->output = 'unavailable'; $metrics = [ 'diskFree' => false, 'diskTotal' => 0 ];
checkSystem( $windows->snapshot() === [ 'cpu' => 'N/A', 'memory' => 'N/A', 'disk' => 'N/A' ], 'Failed readings safe' );
$metrics = [ 'load' => [ INF ], 'peakMemory' => NAN, 'diskFree' => 1e308, 'diskTotal' => 1e-308 ];
checkSystem( json_encode( $stats->snapshot() ) !== false, 'Nonfinite and overflow readings remain JSON-safe' );
$commands->throws = true;
$unavailable = new Statistics( 'Linux', '/', $commands, static function () { throw new RuntimeException(); } );
checkSystem( $unavailable->snapshot() === [ 'cpu' => 'N/A', 'memory' => 'N/A', 'disk' => 'N/A' ], 'Probe exceptions become unavailable values' );
foreach ( [ 'ajax', 'embedded', 'disabled' ] as $mode ) {
	$process = proc_open( [ PHP_BINARY, '-n', __DIR__ . '/system-request.php', $root . '/request-' . $mode, $mode ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	fclose( $pipes[0] ); $output = stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); $errors = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
	checkSystem( proc_close( $process ) === 0 && str_starts_with( $output, 'PASS' ), $output . $errors ); echo $output;
}
echo "PASS system inspection\n";
