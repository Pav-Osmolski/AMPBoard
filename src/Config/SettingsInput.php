<?php

namespace AMPBoard\Config;

/** Normalises form data before any profile or encryption-key writes. */
final class SettingsInput {
	public function normalise( array $input ): array {
		$defs = [
			'DB_HOST'     => FILTER_DEFAULT,
			'DB_USER'     => FILTER_DEFAULT,
			'DB_PASSWORD' => FILTER_DEFAULT,

			'APACHE_PATH'           => FILTER_DEFAULT,
			'HTDOCS_PATH'           => FILTER_DEFAULT,
			'PHP_PATH'              => FILTER_DEFAULT,

			// UI flags
			'displayHeader'         => FILTER_DEFAULT,
			'displayFooter'         => FILTER_DEFAULT,
			'displayClock'          => FILTER_DEFAULT,
			'displaySearch'         => FILTER_DEFAULT,
			'displayTooltips'       => FILTER_DEFAULT,
			'displayFolderBadges'   => FILTER_DEFAULT,
			'displaySystemStats'    => FILTER_DEFAULT,
			'displayApacheErrorLog' => FILTER_DEFAULT,
			'displayPhpErrorLog'    => FILTER_DEFAULT,
			'useAjaxForStats'       => FILTER_DEFAULT,
			'useAjaxForErrorLog'    => FILTER_DEFAULT,

			// Performance flags and theme
			'apacheFastMode'        => FILTER_DEFAULT,
			'mysqlFastMode'         => FILTER_DEFAULT,
			'theme'                 => FILTER_DEFAULT,

			// PHP management
			'displayPhpErrors'      => FILTER_DEFAULT,
			'logPhpErrors'          => FILTER_DEFAULT,
			'phpErrorLevel'         => FILTER_DEFAULT,
			'phpMemoryLimit'        => FILTER_DEFAULT,
			'phpMaxExecution'       => FILTER_DEFAULT,
			'phpMaxInputVars'       => FILTER_DEFAULT,
			'phpUploadMaxFile'      => FILTER_DEFAULT,
			'phpPostMaxSize'        => FILTER_DEFAULT,
			'phpTimezone'           => FILTER_DEFAULT,

			// php.ini path and error_reporting value (override-able)
			'php_ini_path'          => FILTER_DEFAULT,
			'error_reporting_value' => FILTER_DEFAULT,

			// JSON blobs
			'folders_json'          => FILTER_UNSAFE_RAW,
			'link_templates_json'   => FILTER_UNSAFE_RAW,
			'dock_json'             => FILTER_UNSAFE_RAW,
		];

		foreach ( $input as $value ) {
			if ( ! is_scalar($value) && $value !== null ) { throw new \InvalidArgumentException('Settings fields must be scalar.'); }
		}
		$in = filter_var_array( $input, $defs, false );
		// Normalise paths (using helper if available)
		if ( function_exists( 'normalise_path' ) ) {
			foreach ( [ 'APACHE_PATH', 'HTDOCS_PATH', 'PHP_PATH', 'php_ini_path' ] as $k ) {
				if ( isset( $in[ $k ] ) && is_string( $in[ $k ] ) ) {
					$in[ $k ] = normalise_path( $in[ $k ] );
				}
			}
		}


		// Theme: letters, numbers, dashes, underscores. Fallback to default.
		$theme = 'default';
		if ( isset( $in['theme'] ) && is_string( $in['theme'] ) ) {
			$t = trim( $in['theme'] );
			if ( $t !== '' && preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $t ) ) {
				$theme = $t;
			}
		}

		/* ------------------------------------------------------------------ */
		/* DB values and JSON payloads                                        */
		/* ------------------------------------------------------------------ */

		$DB_HOST = isset( $in['DB_HOST'] ) && is_string( $in['DB_HOST'] ) ? trim( $in['DB_HOST'] ) : '';
		$DB_USER = isset( $in['DB_USER'] ) && is_string( $in['DB_USER'] ) ? trim( $in['DB_USER'] ) : '';
		$DB_PASS = isset( $in['DB_PASSWORD'] ) && is_string( $in['DB_PASSWORD'] ) ? trim( $in['DB_PASSWORD'] ) : '';

		if ( $DB_HOST !== '' ) {
			if ( $DB_HOST === 'localhost' ) {
				// always accept
			} elseif ( ! filter_var( $DB_HOST, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME )
					   && ! filter_var( $DB_HOST, FILTER_VALIDATE_IP ) ) {
				throw new \InvalidArgumentException( 'DB_HOST invalid.' );
			}
		}


		$profile = [];
		foreach ( [ 'folders' => 'folders_json', 'linkTemplates' => 'link_templates_json', 'dock' => 'dock_json' ] as $key => $field ) {
			$profile[$key] = json_decode( validate_and_canonicalise_json( (string) ( $in[$field] ?? '' ) ), true );
		}
		$settings = [ 'theme' => $theme ];
		foreach ( ProfileSchema::FLAGS as $field ) { $settings[$field] = normalise_bool( $in[$field] ?? null ) === 'true'; }
		foreach ( [ 'DB_HOST' => $DB_HOST, 'DB_USER' => $DB_USER, 'DB_PASSWORD' => $DB_PASS ] as $field => $value ) {
			if ( $value !== '' ) { $settings[$field] = $value; }
		}
		foreach ( [ 'APACHE_PATH', 'HTDOCS_PATH', 'PHP_PATH' ] as $field ) {
			if ( isset($in[$field]) && is_string($in[$field]) && $in[$field] !== '' ) { $settings[$field] = $in[$field]; }
		}
		$phpSettings = ( new \AMPBoard\Php\SettingsInput() )->normalise( $in );
		return [ 'settings' => $settings, 'php' => $phpSettings['php'], 'profile' => $profile,
			'ini' => $phpSettings['ini'], 'iniPath' => trim((string)($in['php_ini_path'] ?? '')) ];
	}
}
