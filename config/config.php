<?php
/**
 * Global Configuration and Initialisation
 *
 * Bootstraps the application environment, merging user overrides, defining constants,
 * setting defaults, and loading helper utilities for theming, UI behaviour, and diagnostics.
 *
 * Responsibilities:
 * - Load user overrides from `user_config.php` (if available)
 * - Define environment paths: `APACHE_PATH`, `HTDOCS_PATH`, `PHP_PATH`
 * - Define DB constants if not already set: `DB_HOST`, `DB_USER`, `DB_PASSWORD`
 * - Populate UI feature flags and theme selection
 * - Derive current user, body classes, and theme metadata
 * - Decrypt DB credentials using `getDecrypted()`
 * - Provide default tooltip descriptions
 *
 * Assumptions:
 * - Constants `DB_USER` and `DB_PASSWORD` are encrypted and must be decrypted
 * - `toggle_apache.php` is optional and affects `$apacheToggle`
 * - External helpers from `helpers.php` handle security, rendering, detection, and theming
 *
 * Global Outputs:
 * - Paths: `APACHE_PATH`, `HTDOCS_PATH`, `PHP_PATH`
 * - Flags: `$apachePathValid`, `$htdocsPathValid`, `$phpPathValid`, `$apacheToggle`
 * - DB: `$dbUser`, `$dbPass`
 * - UI: `$theme`, `$currentTheme`, `$bodyClasses`, `$tooltips`, `$defaultTooltipMessage`
 * - Toggles: `$displayHeader`, `$displayFooter`, `$displayClock`, `$displaySearch`, `$displayTooltips`, `displayFolderBadges`, `$displaySystemStats`, `$displayApacheErrorLog`, `$displayPhpErrorLog`, `$useAjaxForStats`, `$useAjaxForErrorLog`
 * - Themes: `$themeOptions`, `$themeTypes`
 * - Misc: `$user`, `$currentPhpErrorLevel`
 *
 * Depends On:
 * - `helpers.php` for access control and shared logic like `resolveCurrentUser()`, `buildBodyClasses()`, `loadThemes()`, etc.
 *
 * @author  Pawel Osmolski
 * @version 2.6
 */

require_once __DIR__ . '/helpers.php';

// Load user-specific overrides first
if ( file_exists( __DIR__ . '/user_config.php' ) ) {
	require_once __DIR__ . '/user_config.php';
}

// Initialise Configs
$headingsConfig      = read_json_array_safely( __DIR__ . '/headings.json' );
$tooltipsConfig      = read_json_array_safely( __DIR__ . '/tooltips.json' );
$foldersConfig       = read_json_array_safely( __DIR__ . '/folders.json' );
$linkTemplatesConfig = read_json_array_safely( __DIR__ . '/link_templates.json' );
$dockConfig          = read_json_array_safely( __DIR__ . '/dock.json' );

// Enable Demo Mode (disables saving settings and obfuscates credentials)
const DEMO_MODE = false;

// Export files exclusion list
const EXPORT_EXCLUDE = [
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
	'.turbo'
];

// DB settings with guards
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

// Paths (user-defined values will pass through untouched)
define_path_constant( 'APACHE_PATH', 'C:/xampp/apache' );
define_path_constant( 'HTDOCS_PATH', 'C:/htdocs' );
define_path_constant( 'PHP_PATH', 'C:/xampp/php' );

// Theme and UI display defaults (overridden by user_config.php)
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

// Decrypt current DB User and Password
$dbUser = getDecrypted( 'DB_USER' );
$dbPass = getDecrypted( 'DB_PASSWORD' );

// Check for valid paths and files
$apachePathValid = file_exists( APACHE_PATH );
$htdocsPathValid = file_exists( HTDOCS_PATH );
$phpPathValid    = file_exists( PHP_PATH );
$apacheToggle    = file_exists( __DIR__ . '/../utils/toggle_apache.php' );
$mySqlHostValid  = checkMysqlCredentialsStatus( 'host' );
$mySqlUserValid  = checkMysqlCredentialsStatus( 'user' );
$mySqlPassValid  = checkMysqlCredentialsStatus( 'pass' );

// Get current PHP Error Level
$currentPhpErrorLevel = ini_get( 'error_reporting' );

// Finalise computed environment values using helper functions
$user = ( defined( 'DEMO_MODE' ) && DEMO_MODE ) ? 'demo' : resolveCurrentUser();

// Class list for the <body> based on the UI options set by user_config.php
$bodyClasses = buildBodyClasses(
	$theme,
	$displayHeader,
	$displayFooter,
	$displayClock,
	$displaySearch,
	$displayTooltips,
	$displaySystemStats,
	$displayApacheErrorLog,
	$displayPhpErrorLog
);

// Query available themes directly from assets
[ $themeOptions, $themeTypes ] = loadThemes( __DIR__ . '/../assets/scss/themes/' );

// Set the current theme
$currentTheme = $theme;

// Initialise Tooltips
$tooltips              = getDefaultTooltips();
$defaultTooltipMessage = getDefaultTooltipMessage();
