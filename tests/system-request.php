<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
$root = $argv[1]; $mode = $argv[2];
mkdir( $root . '/utils', 0700, true ); mkdir( $root . '/config' );
copy( __DIR__ . '/../utils/system_stats.php', $root . '/utils/system_stats.php' );
file_put_contents( $root . '/config/config.php', '<?php /* fixture dependencies */' );
$config = [ 'ui' => [ 'flags' => [ 'systemStats' => $mode !== 'disabled', 'useAjaxForStats' => $mode === 'ajax' ] ] ];
$calls = 0;
$runner = new class implements \AMPBoard\Apache\CommandRunner {
	public function run( string $command ): array { return [ 'success' => true, 'output' => '2' ]; }
};
$systemStatistics = new \AMPBoard\System\Statistics( 'Linux', '/', $runner, static function () use ( &$calls ): array {
	++$calls; return [ 'load' => [ 1 ], 'peakMemory' => 2097152, 'diskFree' => 20, 'diskTotal' => 100 ];
} );
ob_start();
register_shutdown_function( static function () use ( $mode, &$calls ): void {
	$body = ob_get_clean();
	if ( $mode === 'disabled' ) { $ok = $calls === 0 && json_decode( $body, true ) === [ 'error' => 'System stats display is disabled.' ]; }
	elseif ( $mode === 'ajax' ) { $ok = $calls === 1 && json_decode( $body, true ) === [ 'cpu' => 50, 'memory' => 2, 'disk' => 20 ]; }
	else { $ok = $calls === 1 && str_contains( $body, "id='cpu-load' aria-live='polite'>50%" ) && str_contains( $body, "id='memory-usage' aria-live='polite'>2 MB" ) && str_contains( $body, "id='disk-space' aria-live='polite'>20%" ); }
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' statistics request ' . $mode . "\n"; exit( $ok ? 0 : 1 );
} );
require $root . '/utils/system_stats.php';
