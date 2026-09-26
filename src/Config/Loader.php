<?php

namespace AMPBoard\Config;

use AMPBoard\Database\ConnectionFactory;
use AMPBoard\Ui\ThemeCatalog;
use AMPBoard\Security\CredentialCipher;

/** Assembles the dashboard configuration from resolved profile data. */
final class Loader {
	private string $directory;
	private ProfileRepository $profiles;

	public function __construct( string $directory, ?ProfileRepository $profiles = null ) {
		$this->directory = $directory;
		$this->profiles = $profiles ?? new ProfileRepository( $directory, new CredentialCipher( dirname( $directory ) . '/.key' ), null, LegacyConstants::read() );
	}

	/** New profiles can load without constants; opt in only at the legacy entry point. */
	public function load( bool $publishLegacyConstants = false ): array {
		$rawUser = resolveCurrentUser();
		$profile = $this->profiles->load( $rawUser );
		$settings = $profile['settings'];
		if ( $publishLegacyConstants ) { LegacyConstants::publish( $settings ); }
		( new PhpSettings() )->apply( $profile['php'] );
		$defaultConfigDir = $profile['default'];
		$userConfigDir = $profile['target'];
		$activeConfigDir = $profile['active'];
		$foldersConfig = $profile['profile']['folders'];
		$linkTemplatesConfig = $profile['profile']['linkTemplates'];
		$dockConfig = $profile['profile']['dock'];
		$interfaceDir = $this->directory . '/interface';
		$headingsConfig = read_json_array_safely( $interfaceDir . '/headings.json' );
		$tooltipsConfig = read_json_array_safely( $interfaceDir . '/tooltips.json' );
		$assetsDir = $this->directory . '/../assets';
		$crtDir = $this->directory . '/../crt';
		$partialsDir = $this->directory . '/../partials';
		$utilsDir = $this->directory . '/../utils';
		$logsDir = $this->directory . '/../logs';
		$dbUser = $profile['db']['user'];
		$dbPass = $profile['db']['pass'];
		$database = new ConnectionFactory( $profile['db'] );
		$apachePathValid = file_exists( $settings['APACHE_PATH'] );
		$htdocsPathValid = file_exists( $settings['HTDOCS_PATH'] );
		$phpPathValid = file_exists( $settings['PHP_PATH'] );

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
		$user = $settings['DEMO_MODE'] ? 'demo' : $rawUser;

		// Base config structure (paths are available early for helpers)
		$config['paths'] = [
			'apache'         => $settings['APACHE_PATH'],
			'htdocs'         => $settings['HTDOCS_PATH'],
			'php'            => $settings['PHP_PATH'],
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
			$settings['theme'],
			$settings['displayHeader'],
			$settings['displayFooter'],
			$settings['displayClock'],
			$settings['displaySearch'],
			$settings['displayTooltips'],
			$settings['displaySystemStats'],
			$settings['displayApacheErrorLog'],
			$settings['displayPhpErrorLog'],
			$systemStatsAvailable,
			$apacheErrorLogAvailable,
			$phpErrorLogAvailable
		);
		// Query available themes directly from assets
		[ $themeOptions, $themeTypes ] = $themes->loadThemes( $this->directory . '/../assets/scss/themes/' );

		// Set the current theme
		$currentTheme = $settings['theme'];

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
				'header'             => $settings['displayHeader'],
				'footer'             => $settings['displayFooter'],
				'clock'              => $settings['displayClock'],
				'search'             => $settings['displaySearch'],
				'tooltips'           => $settings['displayTooltips'],
				'folderBadges'       => $settings['displayFolderBadges'],
				'systemStats'        => $settings['displaySystemStats'],
				'apacheErrorLog'     => $settings['displayApacheErrorLog'],
				'phpErrorLog'        => $settings['displayPhpErrorLog'],
				'useAjaxForStats'    => $settings['useAjaxForStats'],
				'useAjaxForErrorLog' => $settings['useAjaxForErrorLog'],
				'apacheFastMode'     => $settings['apacheFastMode'],
				'mysqlFastMode'      => $settings['mysqlFastMode'],
			],
			'themes'      => [
				'theme'        => $settings['theme'],
				'currentTheme' => $currentTheme,
				'colorScheme'  => $themes->getThemeColorScheme( $settings['theme'] ),
				'options'      => $themeOptions,
				'types'        => $themeTypes,
			],
			'tooltips'    => [
				'map'     => $tooltips,
				'default' => $defaultTooltipMessage,
			],
		];

		$config['db'] = [
			'host' => $settings['DB_HOST'],
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
			'isDemo'            => (bool) $settings['DEMO_MODE'],
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

		$config['export'] = [ 'excludes' => $settings['EXPORT_EXCLUDE'] ];

		return $config;
	}

}
