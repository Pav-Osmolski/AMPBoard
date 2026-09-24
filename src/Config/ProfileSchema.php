<?php

namespace AMPBoard\Config;

/** Shared field names for legacy input and versioned, returned-array profiles. */
final class ProfileSchema {
	public const FLAGS = [ 'displayHeader', 'displayFooter', 'displayClock', 'displaySearch',
		'displayTooltips', 'displayFolderBadges', 'displaySystemStats', 'displayApacheErrorLog',
		'displayPhpErrorLog', 'useAjaxForStats', 'useAjaxForErrorLog', 'apacheFastMode', 'mysqlFastMode' ];
	public const CONSTANTS = [ 'DB_HOST', 'DB_USER', 'DB_PASSWORD', 'APACHE_PATH', 'HTDOCS_PATH', 'PHP_PATH', 'DEMO_MODE', 'EXPORT_EXCLUDE' ];
	public const JSON = [ 'folders' => 'folders.json', 'linkTemplates' => 'link_templates.json', 'dock' => 'dock.json' ];
	public const INI = [ 'display_errors', 'log_errors', 'error_reporting', 'memory_limit', 'max_execution_time',
		'max_input_vars', 'upload_max_filesize', 'post_max_size', 'date.timezone' ];

	public static function defaults(): array {
		return array_merge( array_fill_keys( self::FLAGS, true ), [
			'apacheFastMode' => false, 'mysqlFastMode' => false, 'theme' => 'default',
			'DB_HOST' => 'localhost', 'DB_USER' => 'user', 'DB_PASSWORD' => 'password',
			'APACHE_PATH' => normalise_path( 'C:/xampp/apache' ), 'HTDOCS_PATH' => normalise_path( 'C:/htdocs' ),
			'PHP_PATH' => normalise_path( 'C:/xampp/php' ),
			'DEMO_MODE' => filter_var( getenv( 'AMPBOARD_DEMO_MODE' ) ?: false, FILTER_VALIDATE_BOOLEAN ),
			'EXPORT_EXCLUDE' => [ '.git', '.idea', 'node_modules', 'vendor', 'dist', 'build', '.vscode', '.DS_Store',
				'Thumbs.db', '.cache', '.parcel-cache', '.sass-cache', '.next', '.nuxt', '.turbo' ],
		] );
	}
}
