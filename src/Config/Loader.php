<?php

namespace AMPBoard\Config;

use AMPBoard\Database\ConnectionFactory;
use AMPBoard\Ui\ThemeCatalog;

/** Loads legacy profiles into the central configuration without leaking local variables. */
final class Loader {
	private string $directory;

	public function __construct( string $directory ) {
		$this->directory = $directory;
	}

	/** Load once per request; legacy profiles still define process-wide constants. */
	public function load(): array {
		// Initialise local overrides (e.g. demo mode)
		$localOverrides = $this->directory . '/local.php';

		if ( file_exists( $localOverrides ) ) {
			require_once $localOverrides;
		}

		// Sanitize username for user config folder creation
		$rawUser    = resolveCurrentUser();
		$userFolder = sanitizeFolderName( $rawUser );

		// Default + user config paths
		$defaultConfigDir = $this->directory . '/profiles/default';
		$userConfigDir    = $this->directory . '/profiles/' . $userFolder;

		// Determine active config directory (user specific or default)
		$activeConfigDir  = is_dir( $userConfigDir ) ? $userConfigDir : $defaultConfigDir;
		$activeUserConfig = $activeConfigDir . '/user_config.php';

		// AMPBoard interface configuration directory (shared, non user specific)
		$interfaceDir = $this->directory . '/interface';

		// Load user-specific PHP overrides (if present)
		if ( file_exists( $activeUserConfig ) ) {
			require_once $activeUserConfig;
		}

		// AMPBoard general application paths
		$assetsDir   = $this->directory . '/../assets';
		$crtDir      = $this->directory . '/../crt';
		$partialsDir = $this->directory . '/../partials';
		$utilsDir    = $this->directory . '/../utils';
		$logsDir     = $this->directory . '/../logs';

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
		$database = new ConnectionFactory( [ 'host' => DB_HOST, 'user' => $dbUser, 'pass' => $dbPass ] );

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
		$databaseStatus = $database->credentialStatus();
		$mySqlHostValid = $databaseStatus['host'];
		$mySqlUserValid = $databaseStatus['user'];
		$mySqlPassValid = $databaseStatus['pass'];

		// Current PHP ini settings
		$currentPhpDisplayErrors  = ini_get( 'display_errors' );
		$currentPhpErrorReporting = ini_get( 'error_reporting' );
		$currentPhpLogErrors      = ini_get( 'log_errors' );
		$currentPhpMemoryLimit    = ini_get( 'memory_limit' );
		$currentPhpMaxExecution   = ini_get( 'max_execution_time' );
		$currentPhpMaxInputVars   = ini_get( 'max_input_vars' );
		$currentPhpUploadMaxFile  = ini_get( 'upload_max_filesize' );
		$currentPhpPostMaxSize    = ini_get( 'post_max_size' );
		$currentPhpTimezone       = ini_get( 'date.timezone' );

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
		$themes = new ThemeCatalog( $assetsDir );
		$bodyClasses = $themes->buildBodyClasses(
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
		[ $themeOptions, $themeTypes ] = $themes->loadThemes( $this->directory . '/../assets/scss/themes/' );

		// Set the current theme
		$currentTheme = $theme;

		// Initialise Tooltips
		$tooltips              = $tooltipsConfig;
		$defaultTooltipMessage = 'No description available for this setting.';

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
				'colorScheme'  => $themes->getThemeColorScheme( $theme ),
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
			'name'              => $user,
			'isDemo'            => (bool) ( defined( 'DEMO_MODE' ) && DEMO_MODE ),
			'phpDisplayErrors'  => $currentPhpDisplayErrors,
			'phpErrorReporting' => $currentPhpErrorReporting,
			'phpLogErrors'      => $currentPhpLogErrors,
			'phpMemoryLimit'    => $currentPhpMemoryLimit,
			'phpMaxExecution'   => $currentPhpMaxExecution,
			'phpMaxInputVars'   => $currentPhpMaxInputVars,
			'phpUploadMaxFile'  => $currentPhpUploadMaxFile,
			'phpPostMaxSize'    => $currentPhpPostMaxSize,
			'phpTimezone'       => $currentPhpTimezone,
		];

		$config['interface'] = [
			'headings' => $headingsConfig,
			'tooltips' => $tooltipsConfig,
		];

		return $config;
	}

}
