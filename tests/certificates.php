<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';
use AMPBoard\Certificates\Generator;
use AMPBoard\Certificates\ScriptInstaller;
use AMPBoard\Certificates\Domain;
function check( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function fails( callable $action, string $message ): void {
 $failed = false; try { $action(); } catch ( Throwable $error ) { $failed = true; }
 check( $failed, $message );
}
set_error_handler( static function ( $severity, $message, $file, $line ) {
 if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
 return false;
} );
class CertificateCommands implements AMPBoard\Apache\CommandRunner {
 public array $calls = [];
 public array $result = [ 'success' => true, 'output' => 'Fixture certificate completed.' ];
 public bool $throws = false;
 public function run( string $command ): array {
  $this->calls[] = $command;
  if ( $this->throws ) { throw new RuntimeException( 'Execution unavailable' ); }
  return $this->result;
 }
}
$root = sys_get_temp_dir() . '/ampboard-cert-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/source', 0700, true );
register_shutdown_function( static function () use ( $root ): void {
 $items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
 foreach ( $items as $item ) { $item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
 rmdir( $root );
} );
foreach ( [ 'localhost', 'Example.test', 'xn--bcher-kva.test', '127.0.0.1', str_repeat('a',63).'.test' ] as $name ) { check( Domain::validate($name) === $name, 'Valid hostname preserved' ); }
$commands = new CertificateCommands();
$generator = new Generator( $root . '/untouched', $root . '/source', 'Linux', $commands );
check( ! file_exists( $root . '/untouched' ) && $commands->calls === [], 'Construction is lazy' );
foreach ( [ '', '.', '..', '../name', 'a..test', '*.test', '-option', 'a-.test', 'a/b', 'a b', "a\0b", 'a;whoami', 'a.test.', 'CON', 'nul.test', str_repeat('a',64), str_repeat('a.',127).'a' ] as $name ) {
 fails( static function () use ( $generator, $name ) { $generator->generate( $name ); }, 'Reject invalid domain: ' . $name );
}
check( ! file_exists( $root . '/untouched' ) && $commands->calls === [], 'Validation precedes all writes and execution' );
foreach ( [ 'Windows', 'Linux', 'Darwin' ] as $os ) {
 $extension = $os === 'Windows' ? 'bat' : 'sh';
 file_put_contents( $root . '/source/make-cert-silent.' . $extension, 'fixture script, never executed' );
 $g = new Generator( $root . '/apache space-' . $os, $root . '/source', $os, $commands );
 check( $g->generate( 'example.test' )['success'], 'Platform workflow' );
 check( is_file( $root . '/apache space-' . $os . '/crt/make-cert-silent.' . $extension ), 'Script installed' );
 $command = end( $commands->calls );
 check( str_contains( $command, 'example.test' ) && str_starts_with( $command, $os === 'Windows' ? 'cmd /d /s /c ""' : 'bash ' ), 'Platform command quoting' );
}
file_put_contents( $root . '/source/make-cert-silent.ps1', 'fixture ps1' );
$g = new Generator( $root . '/windows', $root . '/source', 'Windows', $commands ); $g->generate( 'example.test' );
check( str_starts_with( end( $commands->calls ), 'powershell -NoProfile -NonInteractive' ), 'PowerShell preference' );
$target = $root . '/windows/crt/make-cert-silent.ps1';
file_put_contents( $target, 'newer custom' ); touch( $target, time() + 100 ); clearstatcache();
$g->generate( 'example.test' ); check( file_get_contents($target) === 'newer custom', 'Newer installed script preserved' );
touch( $target, 100 ); clearstatcache(); $g->generate( 'example.test' );
check( file_get_contents($target) === 'fixture ps1', 'Outdated script updated' );
$lock = fopen( $root . '/windows/crt/.ampboard-cert.lock', 'c+b' ); flock( $lock, LOCK_EX );
$count = count( $commands->calls );
fails( static function () use ($g) { $g->generate('example.test'); }, 'Concurrent request rejected' );
check( count($commands->calls) === $count, 'No command under held lock' ); flock($lock, LOCK_UN); fclose($lock);
$commands->throws = true; fails( static function () use ($g) { $g->generate('example.test'); }, 'Command exception' );
$commands->throws = false; check( $g->generate('example.test')['success'], 'Lock released after exception' );
$commands->result = [ 'success' => false, 'output' => 'successfully printed, but exit status failed' ];
check( ! $g->generate('example.test')['success'], 'Exit result wins over output' );
$installer = new class extends ScriptInstaller {
 protected function replace( string $source, string $target ): bool { return false; }
};
file_put_contents($target,'retained on failure'); touch($target,100); clearstatcache();
fails( static function () use($installer,$root) { $installer->install($root.'/source',$root.'/windows/crt',['make-cert-silent.ps1']); }, 'Publish failure handled' );
check( file_get_contents($target) === 'retained on failure' && glob($root.'/windows/crt/*.tmp') === [] && glob($root.'/windows/crt/.ampboard-script-*') === [], 'Existing script survives and staging cleaned' );
$installer = new class extends ScriptInstaller {
 protected function copy( string $source, string $target ): bool { return false; }
};
fails( static function () use($installer,$root) { $installer->install($root.'/source',$root.'/windows/crt',['make-cert-silent.ps1']); }, 'Copy failure handled' );
fails( static function () use($root,$commands) { (new Generator($root.'/missing',$root.'/empty','Linux',$commands))->generate('example.test'); }, 'Missing script handled' );
fails( static function () use($root,$commands) { (new Generator($root.'/unsupported',$root.'/source','Other',$commands))->generate('example.test'); }, 'Unsupported platform' );
check( ! file_exists($root.'/unsupported'), 'Unsupported platform has no writes' );
fails( static function () use($root,$commands) { (new Generator($root.'/bad%path',$root.'/source','Windows',$commands))->generate('example.test'); }, 'Windows expansion rejected' );
check( ! file_exists($root.'/bad%path'), 'Unsafe path rejected before writes' );
file_put_contents($root.'/blocked','file');
fails( static function () use($root,$commands) { (new Generator($root.'/blocked',$root.'/source','Linux',$commands))->generate('example.test'); }, 'Directory creation failure' );

// Real shell execution of a benign fixture only; no OpenSSL or installed certificates.
mkdir($root.'/native-source');
$ext = PHP_OS_FAMILY === 'Windows' ? 'bat' : 'sh';
$fixture = PHP_OS_FAMILY === 'Windows' ? "@echo off\r\necho fixture-%~1\r\nexit /b 7\r\n" : "#!/bin/bash\nprintf 'fixture-%s\\n' \"\$1\"\nexit 7\n";
file_put_contents($root.'/native-source/make-cert-silent.'.$ext,$fixture);
$native = new Generator($root.'/native space',$root.'/native-source',PHP_OS_FAMILY,new AMPBoard\Apache\ShellCommandRunner());
$result = $native->generate('example.test');
check( ! $result['success'] && str_contains($result['output'],'fixture-example.test'), 'Native path with spaces, domain argument and nonzero exit' );

// The real endpoint is copied beside an empty composition file; all services are fixtures.
mkdir($root.'/utils'); mkdir($root.'/config');
copy(__DIR__.'/../utils/generate_cert.php',$root.'/utils/generate_cert.php');
file_put_contents($root.'/config/config.php','<?php /* fixture composition */');
file_put_contents( $root . '/config/entry-certificates.php', '<?php require_once __DIR__ . "/config.php";' );

foreach ( [ [null,false,400], ['',false,400], [[],false,400], ['../bad',false,400], ['valid.test',true,403], ['valid.test',false,500] ] as [ $name,$demo,$expected ] ) {
 $config=['user'=>['isDemo'=>$demo]]; $_GET=$name===null?[]:['name'=>$name]; $certificates=$g;
 $before=count($commands->calls); http_response_code(200);
 ob_start(); require $root.'/utils/generate_cert.php'; $out=ob_get_clean();
 check(http_response_code()===$expected && $out!=='','Endpoint status/output');
 if($expected!==500) { check(count($commands->calls)===$before,'Rejected request does not execute'); }
}
$commands->result=['success'=>true,'output'=>'Certificate created.'];
$_GET=['name'=>'valid.test']; $config=['user'=>['isDemo'=>false]];
ob_start(); require $root.'/utils/generate_cert.php'; $out=ob_get_clean();
check(http_response_code()===200 && $out==='Certificate created.','Endpoint success output');
echo "PASS certificate services and endpoint\n";
