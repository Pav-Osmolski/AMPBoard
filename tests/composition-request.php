<?php
/** Real composition files, temporary trusted profiles, and a database double. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/fixtures/database.php';
$mode = $argv[1];
$contracts = require __DIR__ . '/fixtures/composition-contracts.php';
$withoutHelpers = substr( $mode, -11 ) === ':no-helpers';
if ( $withoutHelpers ) { $mode = substr( $mode, 0, -11 ); define( 'AMPBOARD_NO_HELPERS', true ); }
$serviceNames = array_unique( array_merge( [ 'folderOpener' ], ...array_values( $contracts ) ) );
$root = sys_get_temp_dir() . '/ampboard-composition-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/config/profiles/default', 0700, true );
mkdir( $root . '/config/interface', 0700 );
register_shutdown_function( static function () use ( $root ): void {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $files as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
	rmdir( $root );
} );
foreach ( glob( __DIR__ . '/../config/*.php' ) as $file ) {
	if ( preg_match( '/^(application|legacy|config|entry-.+|services-.+)\.php$/', basename( $file ) ) ) { copy( $file, $root . '/config/' . basename( $file ) ); }
}
foreach ( [ 'autoload', 'helpers' ] as $file ) {
	file_put_contents( $root . '/config/' . $file . '.php', '<?php require_once ' . var_export( __DIR__ . '/../config/' . $file . '.php', true ) . ';' );
}
$_SERVER['USERNAME'] = 'composition-fixture';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
$_SERVER['SCRIPT_NAME'] = '/fixture/utils/phpinfo.php';
$GLOBALS['profileReads'] = 0;
file_put_contents( $root . '/config/profiles/default/user_config.php', '<?php ++$GLOBALS["profileReads"]; return ["version"=>1,"settings"=>["DB_HOST"=>"fixture-host","DB_USER"=>"fixture-user","DB_PASSWORD"=>"fixture-pass","theme"=>"default"],"php"=>[]];' );
file_put_contents( $root . '/config/profiles/default/folders.json', '[{"title":"Fixture"}]' );
function checkComposition( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
}
if ( $mode === 'dashboard-header' || $mode === 'header-dashboard' ) {
	// Replace only native command execution; exercise the real entries and server wiring.
	file_put_contents( $root . '/config/services-apache-commands.php', '<?php require_once __DIR__ . "/application.php"; $apacheCommands = new class implements \\AMPBoard\\Apache\\CommandRunner { public function run(string $command): array { return ["success"=>false,"output"=>""]; } };' );
}
if ( $mode === 'legacy-profile' ) {
	file_put_contents( $root . '/config/local.php', '<?php define("DB_HOST", "legacy-host"); $displayHeader = normalise_bool("on") === "true";' );
	file_put_contents( $root . '/config/profiles/default/user_config.php', '<?php ++$GLOBALS["profileReads"]; $theme="dracula"; $displayHeader=false;' );
}
if ( $mode === 'modern' || $mode === 'upgrade' ) {
	require $root . '/config/application.php';
	checkComposition( ! function_exists( 'normalise_bool' ) && ! defined( 'DB_HOST' ) && ! defined( 'CRYPTO_KEY_FILE' ), 'Modern configuration has no compatibility publication' );
	checkComposition( mysqli::$connections === [] && ! isset( $session, $database, $ui, $exports, $apacheControl ), 'Configuration alone performs no database probe or service composition' );
	checkComposition( $config['status']['mySqlHostValid'] === null, 'Unprobed credentials are explicitly unknown' );
	if ( $mode === 'upgrade' ) {
		require $root . '/config/services-ui.php';
		$originalUi = $ui;
		require $root . '/config/config.php';
		checkComposition( $ui === $originalUi && $GLOBALS['profileReads'] === 1, 'Compatibility upgrade retains composed services and profile snapshot' );
	}
} elseif ( $mode === 'compatibility' || $mode === 'no-helpers' || $mode === 'legacy-profile' ) {
	if ( $mode === 'no-helpers' ) { define( 'AMPBOARD_NO_HELPERS', true ); }
	require $root . '/config/config.php';
	checkComposition( count( mysqli::$connections ) === 1 && $config['status']['mySqlHostValid'] === true, 'Complete compatibility composition probes credentials once' );
	checkComposition( isset( $folderPresenter, $folderOpener, $exports, $certificates, $phpInfo, $phpIni, $mysqlInspector, $serverInspector, $systemStatistics, $apacheLog, $phpLog, $csrfTokens ), 'Existing composition variables remain available' );
	checkComposition( function_exists( 'normalise_bool' ) === ( $mode !== 'no-helpers' ), 'Existing helper include switch remains supported' );
	if ( $mode === 'legacy-profile' ) {
		checkComposition( $config['db']['host'] === 'legacy-host' && $config['ui']['themes']['theme'] === 'dracula' && ! $config['ui']['flags']['header'], 'Helper-dependent legacy local input and profile precedence remain supported' );
	}
} elseif ( $mode === 'dashboard-header' || $mode === 'header-dashboard' ) {
	if ( $mode === 'dashboard-header' ) { require $root . '/config/entry-dashboard.php'; }
	require $root . '/config/entry-server.php';
	checkComposition( $serverInspector->inspect()['database']['available'], 'Real header composition returns the shared database result' );
	require $root . '/config/entry-dashboard.php';
	require $root . '/config/entry-settings.php';
	checkComposition( $config['status']['mySqlHostValid'] === true && $serverInspector->inspect()['database']['label'] === '8.0.36' && count( mysqli::$connections ) === 1, 'Dashboard, settings and repeated header inspection share exactly one connection attempt in either order' );
} elseif ( $mode === 'embedded' || $mode === 'embedded-reverse' ) {
	// Dashboard panels share the caller's scope. Exercise both eager-first and lazy-first composition.
	$sequence = [ 'dashboard', 'submit', 'server', 'folders', 'settings', 'vhosts', 'exports', 'php-info', 'ui', 'statistics', 'apache-inspector', 'apache-control', 'certificates', 'mysql', 'apache-log', 'php-log', 'read-config' ];
	if ( $mode === 'embedded-reverse' ) { $sequence = array_reverse( $sequence ); }
	$instances = [];
	$diagnosticsLoaded = false;
	foreach ( array_merge( $sequence, $sequence ) as $entry ) {
		require $root . '/config/entry-' . $entry . '.php';
		foreach ( $instances as $name => $instance ) { checkComposition( $$name === $instance, $entry . ' retains the existing ' . $name ); }
		foreach ( $contracts[$entry] as $name ) {
			checkComposition( isset( $$name ) && is_object( $$name ), $entry . ' provides ' . $name );
			$instances[$name] = $$name;
		}
		$diagnosticsLoaded = $diagnosticsLoaded || in_array( $entry, [ 'dashboard', 'settings' ], true );
		checkComposition( count( mysqli::$connections ) === ( $diagnosticsLoaded ? 1 : 0 ), $entry . ' reuses credential diagnostics without operational connections' );
		checkComposition( $GLOBALS['profileReads'] === 1 && session_status() === PHP_SESSION_NONE, $entry . ' reuses the profile and keeps session startup lazy' );
	}
	checkComposition( $databaseObservation->database()['available'] && count( mysqli::$connections ) === 1, 'Embedded header data reuses credential diagnostics' );
	checkComposition( function_exists( 'normalise_bool' ) === ! $withoutHelpers, 'Embedded composition honors the helper compatibility switch' );
} else {
	checkComposition( isset( $contracts[$mode] ), 'Known entry contract' );
	require $root . '/config/entry-' . $mode . '.php';
	foreach ( $serviceNames as $name ) {
		$required = in_array( $name, $contracts[$mode], true );
		checkComposition( isset( $$name ) === $required, $mode . ( $required ? ' provides ' : ' excludes unrelated ' ) . $name );
		if ( $required ) { checkComposition( is_object( $$name ), $mode . ' supplies a service object for ' . $name ); }
	}
	$diagnostics = in_array( $mode, [ 'dashboard', 'settings' ], true );
	checkComposition( count( mysqli::$connections ) === ( $diagnostics ? 1 : 0 ), $mode . ' performs only its intentional credential probe' );
	checkComposition( $config['status']['mySqlHostValid'] === ( $diagnostics ? true : null ), $mode . ' preserves probed versus unknown credential status' );
	checkComposition( function_exists( 'normalise_bool' ) === ! $withoutHelpers, $mode . ' honors the helper compatibility switch' );
}
checkComposition( $GLOBALS['profileReads'] === 1 && session_status() === PHP_SESSION_NONE, 'Composition reads the profile once and never starts a session' );
if ( isset( $ui ) ) {
	$_SERVER['SCRIPT_NAME'] = '/changed/utils/phpinfo.php';
	checkComposition( str_contains( $ui->renderVersionedAssetsWithBase( null, null ), json_encode( '/fixture/' ) ), 'Real UI composition captures its request script before later changes' );
}
if ( $mode !== 'modern' ) { checkComposition( defined( 'DB_HOST' ) && DB_HOST === $config['db']['host'], 'Legacy constants are still explicitly published' ); }
echo 'PASS composition ' . $mode . ( $withoutHelpers ? ' without helpers' : '' ) . "\n";
