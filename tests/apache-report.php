<?php
/** Apache report fixtures use temporary files and supplied commands only. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';

use AMPBoard\Apache\CommandRunner;
use AMPBoard\Apache\Inspector;
use AMPBoard\Ui\ApacheReport;

function checkApacheReport( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );
$root = sys_get_temp_dir() . '/ampboard-apache-report-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/bin', 0700, true );
register_shutdown_function( static function () use ( $root ): void {
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) { $item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $root );
} );
$binary = $root . '/bin/httpd.exe';
file_put_contents( $binary, 'Fixture only; never executed' ); chmod( $binary, 0700 );
$conf = $root . '/httpd.conf';
file_put_contents( $conf, "Include <include-fixture>\n" );
$environment = $root . '/environ';
file_put_contents( $environment, "APACHE_FIXTURE=<env-fixture>\0" );
$_SERVER['SERVER_SOFTWARE'] = 'Apache <version-fixture>';
$commands = new class implements CommandRunner {
	public array $calls = [];
	public array $answers = [];
	public bool $throws = false;
	public function run( string $command ): array {
		$this->calls[] = $command;
		if ( $this->throws ) { throw new RuntimeException( 'Fixture command unavailable' ); }
		return $this->answers[$command] ?? [ 'success' => false, 'output' => '' ];
	}
};
$commands->answers[escapeshellarg( $binary ) . ' -V'] = [ 'success' => true, 'output' => 'SERVER_CONFIG_FILE="' . $conf . '"' ];
$commands->answers[escapeshellarg( $binary ) . ' -S'] = [ 'success' => true, 'output' => 'VirtualHost <vhost-fixture>' ];
$commands->answers['ps -eo etimes,comm | grep -E "apache|httpd" | head -n1'] = [ 'success' => true, 'output' => '3661 httpd' ];
$full = new Inspector( $root, $commands, false, $environment );
$fast = new Inspector( $root, $commands, true, $environment );
checkApacheReport( $commands->calls === [], 'Constructing independent inspectors performs no probes' );
$snapshot = $full->inspect( 'Linux', '64-bit' );
checkApacheReport( $snapshot['binary'] === $binary && $snapshot['config'] === $conf && $snapshot['includes'] === ['<include-fixture>'], 'Full snapshot collects configured binary, config and includes' );
checkApacheReport( $snapshot['uptime'] === '3661 seconds (1h 1m 1s)' && $snapshot['vhosts'] === 'VirtualHost <vhost-fixture>', 'Full snapshot collects uptime and vhosts' );
checkApacheReport( $snapshot['environment']['APACHE_FIXTURE'] === '<env-fixture>', 'Raw environment remains unescaped in the snapshot' );
$before = count( $commands->calls );
$fastSnapshot = $fast->inspect( 'Linux', '32-bit' );
checkApacheReport( count( $commands->calls ) === $before, 'Fast inspection of a configured binary skips command probes' );
checkApacheReport( $fastSnapshot['uptime'] === null && $fastSnapshot['config'] === null && $fastSnapshot['includes'] === null && $fastSnapshot['vhosts'] === null, 'Fast snapshot marks unrequested probes' );
checkApacheReport( ! isset( $fastSnapshot['environment']['APACHE_FIXTURE'] ), 'Fast snapshot skips proc environment' );

$renderer = new ApacheReport();
$html = $renderer->render( $snapshot, false );
checkApacheReport( str_contains( $html, '&lt;include-fixture&gt;' ) && str_contains( $html, '&lt;env-fixture&gt;' ) && str_contains( $html, '&lt;vhost-fixture&gt;' ), 'Diagnostic sources are escaped once' );
checkApacheReport( strpos( $html, 'Operating System:' ) < strpos( $html, 'Apache Version:' ) && strpos( $html, 'Active Virtual Hosts:' ) < strpos( $html, 'PHP Configuration:' ), 'Report section order remains familiar' );
checkApacheReport( str_starts_with( $html, '<pre>' ) && str_ends_with( $html, 'Inspection complete.</pre>' ), 'Report markup is closed' );
$fastHtml = $renderer->render( $fastSnapshot, false );
checkApacheReport( str_contains( $fastHtml, 'Fast mode: Config/VHosts skipped.' ) && ! str_contains( $fastHtml, 'Uptime (estimated)' ) && ! str_contains( $fastHtml, 'Config File:' ), 'Fast report omits full-only sections' );

// Rendering uses only the captured snapshot, even after source files/globals change.
file_put_contents( $conf, '' );
$_SERVER['SERVER_SOFTWARE'] = 'changed after inspection';
checkApacheReport( $renderer->render( $snapshot, false ) === $html && count( $commands->calls ) === $before, 'Rendering performs no commands or source rereads' );
$empty = $full->inspect( 'Linux', '64-bit' );
checkApacheReport( $empty['includes'] === [] && str_contains( $renderer->render( $empty, false ), 'No Include directives found.' ), 'Existing config without includes retains its message' );
unlink( $conf );
$missing = $full->inspect( 'Linux', '64-bit' );
checkApacheReport( $missing['includes'] === null && ! str_contains( $renderer->render( $missing, false ), 'No Include directives found.' ), 'Missing config does not claim to have inspected its includes' );

foreach ( $commands->answers as &$answer ) { $answer['success'] = false; } unset( $answer );
$failed = $full->inspect( 'Linux', '64-bit' );
checkApacheReport( $failed['config'] === null && $failed['vhosts'] === null && $failed['uptime'] === 'Unavailable', 'Failed command output is unavailable rather than displayed as success' );
$commands->throws = true;
$unavailable = $full->inspect( 'Linux', '64-bit' );
checkApacheReport( $unavailable['config'] === null && $unavailable['vhosts'] === null && $unavailable['uptime'] === 'Unavailable' && isset( $unavailable['ini']['Loaded php.ini'] ), 'Probe exceptions do not interrupt independent sections' );

// Exercise rendering boundaries independently of machine runtime metadata.
$unsafe = $snapshot;
$unsafe['os'] = '<os>';
$unsafe['architecture'] = '<arch>';
$unsafe['sapi'] = '<sapi>';
$unsafe['version'] = "<version>\xFF";
$unsafe['binary'] = '<binary>';
$unsafe['uptime'] = '<uptime>';
$unsafe['config'] = '<config>';
$unsafe['environment'] = ['<key>' => '<value>'];
$unsafe['ini'] = ['Loaded php.ini' => '/secret/long/php.ini', 'Scanned .ini files' => '<scanned>'];
$escaped = $renderer->render( $unsafe, false );
foreach ( ['os', 'arch', 'sapi', 'version', 'binary', 'uptime', 'config', 'key', 'value', 'scanned'] as $field ) {
	checkApacheReport( str_contains( $escaped, '&lt;' . $field . '&gt;' ) && ! str_contains( $escaped, '<' . $field . '>' ), 'Escaping ' . $field );
}
checkApacheReport( str_contains( $escaped, "\xEF\xBF\xBD" ), 'Invalid UTF-8 is substituted' );
$demo = $renderer->render( $unsafe, true );
checkApacheReport( ! str_contains( $demo, '/secret/long/php.ini' ) && str_contains( $demo, 'Loaded php.ini: ****************' ) && str_contains( $demo, '&lt;scanned&gt;' ), 'Demo masking retains only the historical loaded-INI scope' );
foreach ( [ 'a' => '****', '12345' => '************' ] as $value => $mask ) {
	$unsafe['ini']['Loaded php.ini'] = (string) $value;
	checkApacheReport( str_contains( $renderer->render( $unsafe, true ), 'Loaded php.ini: ' . $mask . "\n" ), 'Historical masking length buckets' );
}
$absent = $snapshot;
$absent['binary'] = null; $absent['config'] = null; $absent['includes'] = null; $absent['vhosts'] = null; $absent['sapi'] = null; $absent['uptime'] = 'Unavailable'; $absent['environment'] = [];
$absentHtml = $renderer->render( $absent, false );
checkApacheReport( str_contains( $absentHtml, 'Apache Binary Not Found. Config/VHosts skipped.' ) && str_contains( $absentHtml, 'Unknown or not Apache' ) && str_contains( $absentHtml, 'None detected.' ) && str_contains( $absentHtml, 'Not available on this platform or config' ), 'Unavailable report messages remain intact' );
$absent['binary'] = $binary;
checkApacheReport( str_contains( $renderer->render( $absent, false ), 'Config File: Not detected' ) && str_contains( $renderer->render( $absent, false ), 'VirtualHost information not available' ), 'Unavailable config and vhost messages' );
echo "PASS Apache report collection and rendering\n";
