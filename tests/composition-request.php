<?php
/** Real composition files, temporary trusted profiles, and a database double. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
require __DIR__ . '/fixtures/database.php';
$mode = $argv[1];
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
$GLOBALS['profileReads'] = 0;
file_put_contents( $root . '/config/profiles/default/user_config.php', '<?php ++$GLOBALS["profileReads"]; return ["version"=>1,"settings"=>["DB_HOST"=>"fixture-host","DB_USER"=>"fixture-user","DB_PASSWORD"=>"fixture-pass","theme"=>"default"],"php"=>[]];' );
file_put_contents( $root . '/config/profiles/default/folders.json', '[{"title":"Fixture"}]' );
function checkComposition( bool $ok, string $message ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
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
} else {
	require $root . '/config/entry-' . $mode . '.php';
	checkComposition( mysqli::$connections === [], 'Utility composition performs no dashboard credential probe' );
	checkComposition( ! isset( $exports, $certificates, $folderPresenter, $mysqlInspector, $serverInspector, $folderOpener ), 'Unrelated services are absent from narrow utility composition' );
	if ( $mode === 'read-config' ) { checkComposition( isset( $requestOrigin ) && ! isset( $session, $csrfTokens, $database, $ui ), 'Config reader receives origin policy without session or database initialization' ); }
	if ( $mode === 'php-info' ) { checkComposition( isset( $phpInfo, $ui ) && ! isset( $session, $database ), 'PHP info receives only its rendering services' ); }
	if ( $mode === 'statistics' ) { checkComposition( isset( $systemStatistics, $apacheCommands ) && ! isset( $database, $ui, $session ), 'Statistics has no database/session/rendering dependency' ); }
}
checkComposition( $GLOBALS['profileReads'] === 1 && session_status() === PHP_SESSION_NONE, 'Composition reads the profile once and never starts a session' );
if ( $mode !== 'modern' ) { checkComposition( defined( 'DB_HOST' ) && DB_HOST === $config['db']['host'], 'Legacy constants are still explicitly published' ); }
echo 'PASS composition ' . $mode . "\n";
