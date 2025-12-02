<?php
/**
 * Global Configuration and Initialisation
 *
 * Bootstraps AMPBoard by loading helpers, resolving the current user,
 * preparing user-specific config folders, and initialising paths, UI options,
 * themes, and system defaults.
 *
 * Main Tasks:
 * - Load shared helpers (`helpers.php`)
 * - Resolve and sanitise username for profile directory
 * - Load per-user JSON configs:
 *     - `folders.json`, `link_templates.json`, `dock.json`
 * - Load interface JSON configs:
 *     - `headings.json`, `tooltips.json`
 * - Load user overrides from `user_config.php` (if present)
 * - Define path constants (`APACHE_PATH`, `HTDOCS_PATH`, `PHP_PATH`)
 * - Define DB constants with defaults (`DB_HOST`, `DB_USER`, `DB_PASSWORD`)
 * - Apply UI defaults for themes, stats, logging, and display toggles
 * - Decrypt DB credentials with `getDecrypted()`
 * - Validate system paths and MySQL credentials
 * - Compute `$bodyClasses`, determine `$currentTheme`, load available themes
 * - Initialise tooltip defaults
 *
 * Produces:
 * - User profile directory: `$userConfigDir`
 * - Interface config directory: `$interfaceDir`
 * - UI configs: `$foldersConfig`, `$linkTemplatesConfig`, `$dockConfig`
 * - Interface configs: `$headingsConfig`, `$tooltipsConfig`
 * - System flags: `$apachePathValid`, `$htdocsPathValid`, `$phpPathValid`
 * - DB credentials: `$dbUser`, `$dbPass`
 * - UI state: `$theme`, `$currentTheme`, `$bodyClasses`
 * - Toggle flags: header, footer, clock, search, stats, logs, AJAX
 * - Theme metadata: `$themeOptions`, `$themeTypes`
 * - Misc: `$user`, `$currentPhpErrorLevel`, `DEMO_MODE`, `EXPORT_EXCLUDE`
 *
 * Depends On Helpers:
 * - `resolveCurrentUser()`, `sanitizeFolderName()`
 * - `read_json_array_safely()`, `define_path_constant()`
 * - `checkMysqlCredentialsStatus()`, `buildBodyClasses()`
 * - `loadThemes()`, `getDecrypted()`
 * - `getDefaultTooltips()`, `getDefaultTooltipMessage()`
 *
 * @author  Pawel Osmolski
 * @version 2.8
 */

if ( ! defined( 'AMPBOARD_NO_HELPERS' ) ) {
	require_once __DIR__ . '/helpers.php';
}

// Initialise local overrides (e.g. demo mode)
$localOverrides = __DIR__ . '/local.php';

if ( file_exists( $localOverrides ) ) {
	require_once $localOverrides;
}

// Sanitize username for user config folder creation
$rawUser    = resolveCurrentUser();
$userFolder = sanitizeFolderName( $rawUser );

// Default + user config paths
$defaultConfigDir = __DIR__ . '/profiles/default';
$userConfigDir    = __DIR__ . '/profiles/' . $userFolder;

// Determine active config directory (user specific or default)
$activeConfigDir  = is_dir( $userConfigDir ) ? $userConfigDir : $defaultConfigDir;
$activeUserConfig = $activeConfigDir . '/user_config.php';

// AMPBoard interface configuration directory (shared, non user specific)
$interfaceDir = __DIR__ . '/interface';

// Load user-specific PHP overrides (if present)
if ( file_exists( $activeUserConfig ) ) {
	require_once $activeUserConfig;
}

// AMPBoard general application paths
$assetsDir   = __DIR__ . '/../assets';
$crtDir      = __DIR__ . '/../crt';
$partialsDir = __DIR__ . '/../partials';
$utilsDir    = __DIR__ . '/../utils';
$logsDir     = __DIR__ . '/../logs';

// Initialise user configs
$foldersConfig       = read_json_array_safely( $activeConfigDir . '/folders.json' );
$linkTemplatesConfig = read_json_array_safely( $activeConfigDir . '/link_templates.json' );
$dockConfig          = read_json_array_safely( $activeConfigDir . '/dock.json' );

// Initialise AMPBoard interface configs
$headingsConfig = read_json_array_safely( $interfaceDir . '/headings.json' );
$tooltipsConfig = read_json_array_safely( $interfaceDir . '/tooltips.json' );

// Enable Demo Mode (disables saving settings and obfuscates credentials)
if ( ! defined( 'DEMO_MODE' ) ) {
	$demoEnv = getenv( 'AMPBOARD_DEMO_MODE' );

	if ( $demoEnv !== false ) {
		define( 'DEMO_MODE', filter_var( $demoEnv, FILTER_VALIDATE_BOOLEAN ) );
	} else {
		define( 'DEMO_MODE', false );
	}
}

// Export files exclusion list
if ( ! defined( 'EXPORT_EXCLUDE' ) ) {
	define( 'EXPORT_EXCLUDE', [
		'.git',
		'.idea',
		'node_modules',
		'vendor',
		'dist',
		'build',
		'.vscode',
		'.DS_Store',
		'Thumbs.db',
		'.cache',
		'.parcel-cache',
		'.sass-cache',
		'.next',
		'.nuxt',
		'.turbo',
	] );
}

// Database connection defaults
foreach (
	[
		'DB_HOST'     => 'localhost',
		'DB_USER'     => 'user',
		'DB_PASSWORD' => 'password',
	] as $const => $default
) {
	if ( ! defined( $const ) ) {
		define( $const, $default );
	}
}

// Path defaults (can be overridden by user_config.php)
define_path_constant( 'APACHE_PATH', 'C:/xampp/apache' );
define_path_constant( 'HTDOCS_PATH', 'C:/htdocs' );
define_path_constant( 'PHP_PATH', 'C:/xampp/php' );

// UI defaults (if user_config.php did not define them)
$defaults = [
	'theme'                 => 'default',
	'apacheFastMode'        => false,
	'mysqlFastMode'         => false,
	'displayHeader'         => true,
	'displayFooter'         => true,
	'displayClock'          => true,
	'displaySearch'         => true,
	'displayTooltips'       => true,
	'displayFolderBadges'   => true,
	'displaySystemStats'    => true,
	'displayApacheErrorLog' => true,
	'displayPhpErrorLog'    => true,
	'useAjaxForStats'       => true,
	'useAjaxForErrorLog'    => true,
];

foreach ( $defaults as $key => $value ) {
	if ( ! isset( $$key ) ) {
		$$key = $value;
	}
}

// Decrypt DB credentials using the current key storage
$dbUser = getDecrypted( 'DB_USER' );
$dbPass = getDecrypted( 'DB_PASSWORD' );

// Validate paths for Apache, htdocs, and PHP
$apachePathValid = file_exists( APACHE_PATH );
$htdocsPathValid = file_exists( HTDOCS_PATH );
$phpPathValid    = file_exists( PHP_PATH );

// Check if utilities are available
$apacheToggleAvailable   = file_exists( $utilsDir . '/toggle_apache.php' );
$systemStatsAvailable    = file_exists( $utilsDir . '/system_stats.php' );
$apacheErrorLogAvailable = file_exists( $utilsDir . '/apache_error_log.php' );
$phpErrorLogAvailable    = file_exists( $utilsDir . '/php_error_log.php' );

// Validate MySQL credentials (host, user, password)
$mySqlHostValid = checkMysqlCredentialsStatus( 'host' );
$mySqlUserValid = checkMysqlCredentialsStatus( 'user' );
$mySqlPassValid = checkMysqlCredentialsStatus( 'pass' );

// Current PHP error reporting level
$currentPhpErrorLevel = ini_get( 'error_reporting' );

// Resolve display user (respecting DEMO_MODE)
$user = ( defined( 'DEMO_MODE' ) && DEMO_MODE ) ? 'demo' : $rawUser;

// Base config structure (paths are available early for helpers)
$config['paths'] = [
	'apache'         => APACHE_PATH,
	'htdocs'         => HTDOCS_PATH,
	'php'            => PHP_PATH,
	'defaultProfile' => $defaultConfigDir,
	'userProfile'    => $userConfigDir,
	'activeProfile'  => $activeConfigDir,
	'interface'      => $interfaceDir,
	'assets'         => $assetsDir,
	'crt'            => $crtDir,
	'logs'           => $logsDir,
	'partials'       => $partialsDir,
	'utils'          => $utilsDir,
];

// Class list for the <body> based on the UI options set by `user_config.php`
$bodyClasses = buildBodyClasses(
	$theme,
	$displayHeader,
	$displayFooter,
	$displayClock,
	$displaySearch,
	$displayTooltips,
	$displaySystemStats,
	$displayApacheErrorLog,
	$displayPhpErrorLog,
	$systemStatsAvailable,
	$apacheErrorLogAvailable,
	$phpErrorLogAvailable
);
// Query available themes directly from assets
[ $themeOptions, $themeTypes ] = loadThemes( __DIR__ . '/../assets/scss/themes/' );

// Set the current theme
$currentTheme = $theme;

// Initialise Tooltips
$tooltips              = getDefaultTooltips();
$defaultTooltipMessage = getDefaultTooltipMessage();

// Augment the centralised config array
$config['profile'] = [
	'folders'       => $foldersConfig,
	'linkTemplates' => $linkTemplatesConfig,
	'dock'          => $dockConfig,
];

$config['ui'] = [
	'bodyClasses' => $bodyClasses,
	'flags'       => [
		'header'             => $displayHeader,
		'footer'             => $displayFooter,
		'clock'              => $displayClock,
		'search'             => $displaySearch,
		'tooltips'           => $displayTooltips,
		'folderBadges'       => $displayFolderBadges,
		'systemStats'        => $displaySystemStats,
		'apacheErrorLog'     => $displayApacheErrorLog,
		'phpErrorLog'        => $displayPhpErrorLog,
		'useAjaxForStats'    => $useAjaxForStats,
		'useAjaxForErrorLog' => $useAjaxForErrorLog,
		'apacheFastMode'     => $apacheFastMode,
		'mysqlFastMode'      => $mysqlFastMode,
	],
	'themes'      => [
		'theme'        => $theme,
		'currentTheme' => $currentTheme,
		'options'      => $themeOptions,
		'types'        => $themeTypes,
	],
	'tooltips'    => [
		'map'     => $tooltips,
		'default' => $defaultTooltipMessage,
	],
];

$config['db'] = [
	'host' => DB_HOST,
	'user' => $dbUser,
	'pass' => $dbPass,
];

$config['status'] = [
	'apachePathValid'         => $apachePathValid,
	'htdocsPathValid'         => $htdocsPathValid,
	'phpPathValid'            => $phpPathValid,
	'mySqlHostValid'          => $mySqlHostValid,
	'mySqlUserValid'          => $mySqlUserValid,
	'mySqlPassValid'          => $mySqlPassValid,
	'apacheToggleAvailable'   => $apacheToggleAvailable,
	'systemStatsAvailable'    => $systemStatsAvailable,
	'apacheErrorLogAvailable' => $apacheErrorLogAvailable,
	'phpErrorLogAvailable'    => $phpErrorLogAvailable,
];

$config['user'] = [
	'name'     => $user,
	'isDemo'   => (bool) ( defined( 'DEMO_MODE' ) && DEMO_MODE ),
	'phpError' => $currentPhpErrorLevel,
];

$config['interface'] = [
	'headings' => $headingsConfig,
	'tooltips' => $tooltipsConfig,
];
