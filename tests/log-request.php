<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
$root = $argv[1]; $kind = $argv[2]; $mode = $argv[3];
mkdir( $root . '/utils', 0700, true ); mkdir( $root . '/config' ); mkdir( $root . '/partials' );
copy( __DIR__ . '/../utils/' . $kind . '_error_log.php', $root . '/utils/log.php' );
copy( __DIR__ . '/../partials/info.php', $root . '/partials/info.php' );
file_put_contents( $root . '/config/config.php', '<?php /* fixture services */' );
$source = "old\n<script>&unsafe</script>\ninvalid: \xff\n"; file_put_contents( $root . '/log', $source );
$reader = new class extends \AMPBoard\Logs\TailReader {
	public int $calls = 0;
	protected function open( string $path ) { ++$this->calls; return parent::open( $path ); }
};
$apacheLog = new \AMPBoard\Logs\Viewer( [ $root . '/log' ], $reader, 'Apache', 5 );
$phpLog = new \AMPBoard\Logs\Viewer( [ $root . '/log' ], $reader, 'PHP', 25, true );
$config = [ 'paths' => [ 'assets' => dirname( __DIR__ ) . '/assets' ], 'ui' => [ 'flags' => [
	'apacheErrorLog' => $kind === 'apache' && $mode !== 'disabled', 'phpErrorLog' => $kind === 'php' && $mode !== 'disabled',
	'systemStats' => false, 'useAjaxForStats' => $mode !== 'ajax', 'useAjaxForErrorLog' => $mode === 'ajax' ] ] ];
ob_start();
register_shutdown_function( static function () use ( $reader, $kind, $mode, $source ): void {
	$body = ob_get_clean();
	if ( $mode === 'disabled' ) { $ok = $reader->calls === 0 && json_decode( $body, true ) === [ 'error' => ( $kind === 'php' ? 'PHP' : 'Apache' ) . ' error log display is disabled.' ]; }
	elseif ( $mode === 'ajax' ) { $ok = $reader->calls === 1 && $body === ( $kind === 'apache' ? $source : rtrim( $source, "\n" ) ); }
	else { $ok = $reader->calls === 1 && str_contains( $body, '&lt;script&gt;&amp;unsafe&lt;/script&gt;' ) && str_contains( $body, "invalid: \xef\xbf\xbd" ) && str_contains( $body, 'toggle-' . $kind . '-error-log' ) && ! str_contains( $body, 'Loading...' ); }
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' log request ' . $kind . ' ' . $mode . "\n"; exit( $ok ? 0 : 1 );
} );
require $mode === 'panel' ? $root . '/partials/info.php' : $root . '/utils/log.php';
