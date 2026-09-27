<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/../config/autoload.php';

use AMPBoard\System\Identity;
use AMPBoard\Filesystem\Path;
use AMPBoard\Filesystem\FolderName;
use AMPBoard\Filesystem\DirectoryCatalog;

function check( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );
$never = static function () { throw new RuntimeException( 'Unexpected identity discovery' ); };
foreach ( [
	[ [ 'USERNAME' => 'DOMAIN\\Alice', 'USER' => 'ignored' ], 'Alice' ],
	[ [ 'USER' => 'alice@example' ], 'alice' ],
	[ [ 'USERNAME' => '', 'USER' => 'ignored' ], 'Guest' ],
	[ [ 'USERNAME' => null, 'USER' => 'bob' ], 'bob' ],
	[ [ 'USER' => '0' ], 'Guest' ],
] as [ $server, $expected ] ) {
	check( ( new Identity( $server, $never ) )->user() === $expected, 'User precedence and historical fallback' );
}
check( ( new Identity( [], static function () { return null; } ) )->user() === 'Guest', 'Unavailable discovery' );
check( ( new Identity( [], static function () { return "  DOMAIN\\Carol\r\n"; } ) )->user() === 'Carol', 'Trim command output' );
$calls = 0;
$identity = new Identity( [], static function () use ( &$calls ) { ++$calls; return 'original'; } );
$_SERVER['USERNAME'] = 'changed';
check( $identity->user() === 'original' && $identity->user() === 'original' && $calls === 1, 'Immutable request identity' );
foreach ( [ '' => 'localhost', '127.0.0.1' => 'localhost', '::1' => 'localhost',
	'0:0:0:0:0:0:0:1' => 'localhost', '192.168.1.2' => 'localhost', '10.1.2.3' => 'localhost',
	'172.15.0.1' => 'server', '172.16.0.1' => 'localhost', '172.31.0.1' => 'localhost',
	'172.32.0.1' => 'server', '203.0.113.1' => 'server' ] as $remote => $expected ) {
	check( Identity::label( [ 'REMOTE_ADDR' => $remote ] ) === $expected, 'Server label: ' . $remote );
}
check( Identity::label( [ 'REMOTE_ADDR' => '203.0.113.1', 'SERVER_ADDR' => '::1' ] ) === 'localhost', 'Loopback server address' );
check( Path::normalise( 'C:/sites\\project/' ) === 'C:' . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'project', 'Mixed separators' );
check( Path::normalise( '/' ) === '' && Path::normalise( '' ) === '', 'Historical root and empty normalisation' );
foreach ( [ 'Alice Smith' => 'Alice_Smith', '..alice..' => 'alice', 'CON' => 'user_CON',
	'lpt9' => 'user_lpt9', 'COM10' => 'COM10', 'CON.txt' => 'CON.txt', '___' => 'user', 'a-b.c_d' => 'a-b.c_d' ] as $input => $expected ) {
	check( FolderName::sanitise( $input ) === $expected, 'Stable profile directory: ' . $input );
}
// All of the above work without loading any procedural helpers.
check( ! function_exists( 'normalise_path' ), 'Services load independently' );
$root = sys_get_temp_dir() . '/ampboard-identity-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/one/project10', 0750, true );
mkdir( $root . '/one/Project2' );
mkdir( $root . '/two/other', 0750, true );
file_put_contents( $root . '/one/file.txt', 'fixture' );
register_shutdown_function( static function () use ( $root ): void {
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) { $item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $root );
} );
define( 'HTDOCS_PATH', $root . '/wrong-root' );
$one = new DirectoryCatalog( $root . '/one' );
$two = new DirectoryCatalog( $root . '/two' );
check( $one->listDirectories( $one->resolve( '' )['dir'] ) === [ 'Project2', 'project10' ], 'Natural order, directories only, explicit root' );
check( $two->listDirectories( $two->resolve( '' )['dir'] ) === [ 'other' ], 'Independent roots' );
check( $one->listDirectories( $root . '/missing' ) === [] && $one->listDirectories( $root . '/one/file.txt' ) === [], 'Missing and non-directory targets' );
foreach ( [ '../two', 'nested/../two', '..\\two', 'project..old' ] as $relative ) {
	check( $one->resolve( $relative )['dir'] === '' && $one->resolve( $relative )['error'] !== null, 'Historical traversal rejection' );
}
check( is_dir( $one->resolve( '/Project2/' )['dir'] ), 'Leading separators remain relative to the document root' );

require __DIR__ . '/../config/helpers.php';
require __DIR__ . '/fixtures/database.php';
mkdir( $root . '/config/profiles/default', 0750, true );
mkdir( $root . '/config/profiles/Alice_Smith', 0750, true );
mkdir( $root . '/config/interface' );
file_put_contents( $root . '/config/profiles/default/user_config.php', '<?php return ["version"=>1,"settings"=>["theme"=>"overcast"],"php"=>[]];' );
file_put_contents( $root . '/config/profiles/Alice_Smith/user_config.php', '<?php return ["version"=>1,"settings"=>["theme"=>"dracula"],"php"=>[]];' );
$alice = new Identity( [ 'USERNAME' => 'Alice Smith', 'REMOTE_ADDR' => '203.0.113.1' ], $never );
$bob = new Identity( [ 'USER' => 'bob' ], $never );
$first = ( new AMPBoard\Config\Loader( $root . '/config', $alice ) )->load();
$second = ( new AMPBoard\Config\Loader( $root . '/config', $bob ) )->load();
check( $first['user']['name'] === 'Alice Smith' && $first['ui']['themes']['theme'] === 'dracula', 'Existing sanitised profile selected' );
check( $second['user']['name'] === 'bob' && $second['ui']['themes']['theme'] === 'overcast', 'Independent identity uses default profile' );
check( $first['system']['serverLabel'] === 'server' && $second['system']['serverLabel'] === 'localhost', 'Independent display snapshots' );
check( normalise_path( 'a/b/' ) === Path::normalise( 'a/b/' ) && sanitizeFolderName( 'CON' ) === 'user_CON', 'Legacy path/name wrappers' );
check( normalise_subdir( 'site' )['dir'] === HTDOCS_PATH . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR, 'Legacy constant wrapper' );
check( list_subdirs( $root . '/two' ) === [ 'other' ] && resolveCurrentUser() === 'changed', 'Legacy directory/identity wrappers' );
echo "PASS identity and filesystem services\n";
