<?php
/** Apache fixtures never start, stop, or reconfigure a real server. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';

use AMPBoard\Apache\CommandRunner;
use AMPBoard\Apache\Controller;
use AMPBoard\Apache\Inspector;
use AMPBoard\Apache\VhostCatalog;

function expectApache( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );
final class ApacheCommands implements CommandRunner {
	public array $calls = [];
	public array $answers = [];
	public function run( string $command ): array {
		$this->calls[] = $command;
		return $this->answers[ $command ] ?? [ 'output' => '', 'success' => false ];
	}
}
$root = sys_get_temp_dir() . '/ampboard-apache-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/stack one/conf/extra', 0700, true );
register_shutdown_function( static function () use ( $root ): void {
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) { $item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $root );
} );
$stack = $root . '/stack one';
$hosts = $root . '/hosts';
file_put_contents( $hosts, "127.0.0.1 example.test Alias.test # comment.test\n::1 EXAMPLE.TEST\n" );
$vhostsFile = $stack . '/conf/extra/httpd-vhosts.conf';
file_put_contents( $vhostsFile, <<<'CONF'
# <VirtualHost *:80>
<VirtualHost *:80>
ServerName example.test
DocumentRoot "C:/htdocs/example"
</VirtualHost>
<VirtualHost *:443>
ServerName example.test
SSLCertificateFile "ignored-by-managed-convention.crt"
</VirtualHost>
<VirtualHost *:80>
ServerName missing.test
</VirtualHost>
<VirtualHost *:443>
ServerName unfinished.test
CONF
);
mkdir( $stack . '/crt/example.test', 0700, true );
file_put_contents( $stack . '/crt/example.test/server.crt', 'fixture' );
file_put_contents( $stack . '/crt/example.test/server.key', 'fixture' );
$catalog = new VhostCatalog( $stack, [ $hosts, $root . '/missing-hosts' ] );
$data = $catalog->getVhostServerData();
expectApache( $catalog->getHostsEntries() === [ 'example.test', 'alias.test' ], 'Host aliases, case, duplicates and inline comments' );
expectApache( $data['example.test']['valid'] && $data['example.test']['_duplicate'] && $data['example.test']['ssl'], 'Duplicate host uses last block and stays flagged' );
expectApache( $data['example.test']['certValid'] && str_ends_with( str_replace( '\\', '/', $data['example.test']['cert'] ), '/crt/example.test/server.crt' ), 'Managed certificate convention retained' );
expectApache( ! $data['missing.test']['valid'] && ! $data['unfinished.test']['certValid'], 'Missing hosts/certificates and unfinished final block' );
expectApache( $catalog->isValidVhostHost( ' EXAMPLE.TEST ' ) && ! $catalog->isValidVhostHost( '' ), 'Folder filtering lookup' );
expectApache( ( new VhostCatalog( $stack, [] ) )->getValidVhostHostnames() === [], 'Catalogs do not share host validity caches' );
expectApache( ( new VhostCatalog( $root . '/other-stack', [ $hosts ] ) )->getVhostServerData() === [], 'Installations do not share parsed caches' );
file_put_contents( $vhostsFile, '' );
expectApache( $catalog->getVhostServerData() === $data, 'Catalog snapshot remains stable for the request' );

$commands = new ApacheCommands();
$inspector = new Inspector( $stack, $commands );
mkdir( $stack . '/bin' );
$binary = $stack . '/bin/httpd.exe';
file_put_contents( $binary, 'fixture only; never executed' );
chmod( $binary, 0700 );
expectApache( $inspector->detectApacheBinary() === $binary && $commands->calls === [], 'Configured directory resolves to its executable before system fallbacks' );
$versionCommand = escapeshellarg( $binary ) . ' -V';
foreach ( [ 'conf/httpd.conf' => '/srv/apache/conf/httpd.conf', '/etc/apache2/apache2.conf' => '/etc/apache2/apache2.conf', 'C:/Apache/conf/httpd.conf' => 'C:/Apache/conf/httpd.conf' ] as $file => $expected ) {
	$commands->answers[ $versionCommand ] = [ 'output' => 'HTTPD_ROOT="/srv/apache" SERVER_CONFIG_FILE="' . $file . '"', 'success' => true ];
	expectApache( $inspector->getApacheConfigPath( $binary ) === $expected, 'Quoted executable and absolute/relative config paths' );
}
$conf = $root . '/httpd.conf';
file_put_contents( $conf, "# Include ignored.conf\nInclude conf/extra/httpd-vhosts.conf\n IncludeOptional \"conf/enabled/*.conf\"\n" );
expectApache( $inspector->getIncludes( $conf ) === [ 'conf/extra/httpd-vhosts.conf', '"conf/enabled/*.conf"' ], 'Include directives preserve their display form' );
$commands->answers[ escapeshellarg( $binary ) . ' -S' ] = [ 'output' => 'VirtualHost configuration: example.test', 'success' => true ];
expectApache( str_contains( $inspector->getVirtualHosts( $binary ), 'example.test' ), 'Virtual host command output' );
$commands->answers['ps -eo etimes,comm | grep -E "apache|httpd" | head -n1'] = [ 'output' => '3661 httpd', 'success' => true ];
expectApache( $inspector->getApacheUptimeEstimate( 'Linux' ) === '3661 seconds (1h 1m 1s)', 'Uptime output' );
$proc = $root . '/environ';
file_put_contents( $proc, "APACHE_FIXTURE=from-proc\0UNRELATED=value\0" );
$fast = new Inspector( $stack, $commands, true, $proc );
$full = new Inspector( $stack, $commands, false, $proc );
$before = count( $commands->calls );
expectApache( $fast->getApacheConfigPath( $binary ) === null && $fast->getVirtualHosts( $binary ) === null && $fast->getApacheUptimeEstimate( 'Linux' ) === 'Unavailable', 'Fast mode skips expensive probes' );
expectApache( count( $commands->calls ) === $before && ! isset( $fast->getApacheEnvVars()['APACHE_FIXTURE'] ), 'Fast mode skips commands and proc environment' );
expectApache( $full->getApacheEnvVars()['APACHE_FIXTURE'] === 'from-proc', 'Full inspector remains independent of fast inspector' );

$commands = new ApacheCommands();
$exists = static function ( string $path ): bool { return str_starts_with( $path, 'C:/Apache Stack/' ); };
$control = new Controller( 'C:/Apache Stack', 'Windows', $commands, $exists );
expectApache( $commands->calls === [], 'Construction does not run commands' );
$commands->answers['tasklist /FI "IMAGENAME eq httpd.exe"'] = [ 'output' => 'httpd.exe', 'success' => true ];
$restart = 'start /B "ApacheRestart" "C:/Apache Stack/bin/httpd.exe" -k restart';
expectApache( $control->restartCommand() === $restart && ! in_array( $restart, $commands->calls, true ), 'Selecting a restart command does not execute it' );
$commands->answers[ $restart ] = [ 'output' => 'restarted', 'success' => true ];
expectApache( $control->restart() === [ 'success' => true, 'message' => 'Apache restart command executed successfully.', 'output' => 'restarted' ], 'Restart response preserved' );
$commands->answers[ $restart ] = [ 'output' => 'access denied', 'success' => false ];
expectApache( ! $control->restart()['success'], 'Failed restart stays failed' );
$commands->answers = [ 'sc query Apache2.4' => [ 'output' => 'RUNNING', 'success' => true ] ];
expectApache( $control->restartCommand() === 'net stop Apache2.4 && net start Apache2.4', 'Windows service fallback' );
$commands->answers = [];
expectApache( $control->restartCommand() === '"C:/Apache Stack/apache_stop.bat" && "C:/Apache Stack/apache_start.bat"', 'Windows batch fallback' );
$none = static function ( string $path ): bool { return false; };
expectApache( ( new Controller( '', 'Windows', $commands, $none ) )->restart() === [ 'success' => false, 'message' => 'Unable to determine command' ], 'Unknown Windows installation does not execute a restart' );
foreach ( [ 'Linux' => 'sudo systemctl restart apache2', 'Darwin' => 'sudo apachectl restart', 'Unknown' => '' ] as $os => $expected ) {
	expectApache( ( new Controller( '', $os, $commands, $none ) )->restartCommand() === $expected, 'Platform fallback ' . $os );
}
foreach ( [ 'Linux' => '/usr/sbin/apache2ctl', 'Darwin' => '/Applications/MAMP/Library/bin/apachectl' ] as $os => $path ) {
	$tool = static function ( string $candidate ) use ( $path ): bool { return $candidate === $path; };
	expectApache( ( new Controller( '', $os, $commands, $tool ) )->restartCommand() === 'sudo ' . $path . ' restart', 'Installed platform tool ' . $os );
}
echo "PASS Apache services\n";

// Exercise only benign PHP commands, including the real proc_open fallback.
foreach ( [ '', 'exec', 'exec,proc_open' ] as $disabled ) {
	$process = proc_open( [ PHP_BINARY, '-n', '-d', 'disable_functions=' . $disabled, __DIR__ . '/apache-runner.php' ],
		[ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
	$errors = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
	expectApache( proc_close( $process ) === 0 && str_starts_with( $output, 'PASS' ), $output . $errors );
	echo $output;
}

foreach ( [ 'valid', 'failure', 'invalid', 'demo', 'unknown', 'inspect', 'inspect-fast' ] as $scenario ) {
	$process = proc_open( [ PHP_BINARY, '-n', __DIR__ . '/apache-request.php', $root . '/request-' . $scenario, $scenario ],
		[ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] ); fclose( $pipes[1] );
	$errors = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
	expectApache( proc_close( $process ) === 0 && str_starts_with( $output, 'PASS' ), $output . $errors );
	echo $output;
}
