<?php
/** Actual restart endpoint with a simulated command runner and isolated composition. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
$root = $argv[1];
$scenario = $argv[2];
mkdir( $root . '/utils', 0700, true );
mkdir( $root . '/config' );
copy( __DIR__ . '/../utils/toggle_apache.php', $root . '/utils/toggle_apache.php' );
copy( __DIR__ . '/../utils/apache_inspector.php', $root . '/utils/apache_inspector.php' );
file_put_contents( $root . '/config/config.php', '<?php /* Fixture dependencies supplied explicitly. */' );
$commands = new class( $scenario ) implements \AMPBoard\Apache\CommandRunner {
	public array $calls = [];
	private string $scenario;
	public function __construct( string $scenario ) { $this->scenario = $scenario; }
	public function run( string $command ): array {
		$this->calls[] = $command;
		if ( str_ends_with( $command, ' -V' ) ) { return [ 'output' => 'SERVER_CONFIG_FILE="/fixture/httpd.conf"', 'success' => true ]; }
		if ( str_ends_with( $command, ' -S' ) ) { return [ 'output' => 'VirtualHost fixture', 'success' => true ]; }
		return [ 'output' => count( $this->calls ) === 1 ? 'httpd.exe' : 'fixture output', 'success' => $this->scenario !== 'failure' ];
	}
};
$apacheControl = new \AMPBoard\Apache\Controller( $scenario === 'unknown' ? '' : 'C:/Apache', 'Windows', $commands, static function () { return true; } );
$_POST['action'] = $scenario === 'invalid' ? 'stop' : 'restart';
define( 'DEMO_MODE', $scenario === 'demo' );
$inspection = str_starts_with( $scenario, 'inspect' );
if ( $inspection ) {
	function obfuscate_value( $value ) { return $value; }
	$apacheCommands = $commands;
	$vhosts = new \AMPBoard\Apache\VhostCatalog( $root, [] );
	$ui = new class { public function renderHeading( ...$args ) { return 'Apache Inspector'; } };
	$config = [ 'paths' => [ 'apache' => $root ], 'ui' => [ 'flags' => [ 'apacheFastMode' => false ] ] ];
	$_GET['fast'] = $scenario === 'inspect-fast' ? '1' : '0';
	mkdir( $root . '/bin' );
	file_put_contents( $root . '/bin/httpd.exe', 'fixture; never executed' );
	chmod( $root . '/bin/httpd.exe', 0700 );
}
ob_start();
register_shutdown_function( static function () use ( $scenario, $commands, $inspection, &$vhosts ): void {
	$body = ob_get_clean();
	if ( $inspection ) {
		$fast = $scenario === 'inspect-fast';
		$ok = str_contains( $body, $fast ? 'Fast mode: Config/VHosts skipped.' : 'VirtualHost fixture' )
			&& ( $fast ? count( $commands->calls ) === 0 : count( $commands->calls ) > 0 )
			&& $vhosts instanceof \AMPBoard\Apache\VhostCatalog;
		echo ( $ok ? 'PASS' : 'FAIL' ) . ' Apache request ' . $scenario . "\n";
		exit( $ok ? 0 : 1 );
	}
	$result = json_decode( $body, true );
	$expected = [
		'valid' => [ 'success' => true, 'message' => 'Apache restart command executed successfully.', 'output' => 'fixture output' ],
		'failure' => [ 'success' => false, 'message' => 'Failed to restart Apache.', 'output' => 'fixture output' ],
		'invalid' => [ 'success' => false, 'message' => 'Invalid action' ],
		'demo' => [ 'success' => false, 'message' => 'Apache control is disabled in demo mode' ],
		'unknown' => [ 'success' => false, 'message' => 'Unable to determine command' ],
	];
	$ok = $result === $expected[ $scenario ] && count( $commands->calls ) === ( in_array( $scenario, [ 'valid', 'failure' ], true ) ? 2 : 0 );
	$ok = $ok && ( $scenario !== 'demo' || http_response_code() === 403 );
	echo ( $ok ? 'PASS' : 'FAIL' ) . ' Apache request ' . $scenario . "\n";
	exit( $ok ? 0 : 1 );
} );
require $root . '/utils/' . ( $inspection ? 'apache_inspector.php' : 'toggle_apache.php' );
