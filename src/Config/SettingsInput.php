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

		$displayPhpErrors = normalise_bool( $in['displayPhpErrors'] ?? null );
		$logPhpErrors = normalise_bool( $in['logPhpErrors'] ?? null );

		// Theme: letters, numbers, dashes, underscores. Fallback to default.
		$theme = 'default';
		if ( isset( $in['theme'] ) && is_string( $in['theme'] ) ) {
			$t = trim( $in['theme'] );
			if ( $t !== '' && preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $t ) ) {
				$theme = $t;
			}
		}

		/* ------------------------------------------------------------------ */
		/* PHP runtime / limits normalisation                                 */
		/* ------------------------------------------------------------------ */

		/* memory_limit: 256M, 1G, or -1 for unlimited */
		$phpMemoryLimit = isset( $in['phpMemoryLimit'] ) && is_string( $in['phpMemoryLimit'] )
			? normaliseIniSizeOption( $in['phpMemoryLimit'], true, true )
			: null;

		/* max_execution_time: integer seconds, or -1 for unlimited */
		$phpMaxExecution = normaliseIniIntOption( $in['phpMaxExecution'] ?? null, true );

		/* max_input_vars: positive integer */
		$phpMaxInputVars = normaliseIniIntOption( $in['phpMaxInputVars'] ?? null, false );

		/* upload_max_filesize: size string (e.g. 20M, 50M), unit optional */
		$phpUploadMaxFile = isset( $in['phpUploadMaxFile'] ) && is_string( $in['phpUploadMaxFile'] )
			? normaliseIniSizeOption( $in['phpUploadMaxFile'], false, false )
			: null;

		/* post_max_size: size string (e.g. 20M, 50M), unit optional */
		$phpPostMaxSize = isset( $in['phpPostMaxSize'] ) && is_string( $in['phpPostMaxSize'] )
			? normaliseIniSizeOption( $in['phpPostMaxSize'], false, false )
			: null;

		/* date.timezone: light sanity check, not overly strict */
		$phpTimezone = null;
		if ( isset( $in['phpTimezone'] ) && is_string( $in['phpTimezone'] ) ) {
			$val = trim( $in['phpTimezone'] );

			// Rough pattern: "Region/Name" or similar
			if ( $val !== '' && preg_match( '/^[A-Za-z0-9_\/+\-]+$/', $val ) ) {
				$phpTimezone = $val;
			}
		}

		/* ------------------------------------------------------------------ */
		/* PHP error level handling (names or numeric)                        */
		/* ------------------------------------------------------------------ */

		// Accept names (E_ALL, E_ERROR, E_WARNING, E_NOTICE) or numeric values.
		$phpErrorLevels = [
			'E_ALL'     => E_ALL,
			'E_ERROR'   => E_ERROR,
			'E_WARNING' => E_WARNING,
			'E_NOTICE'  => E_NOTICE,
		];

		$phpErrorLevelExpr = 'E_ALL'; // default for error_reporting()

		if ( isset( $in['phpErrorLevel'] ) ) {
			$raw = $in['phpErrorLevel'];

			if ( is_string( $raw ) ) {
				$val = trim( $raw );

				// Named constant, e.g. "E_ALL"
				if ( isset( $phpErrorLevels[ $val ] ) ) {
					$phpErrorLevelExpr = $val;
				} // Numeric value, e.g. "32767"
				elseif ( is_numeric( $val ) ) {
					$ival = (int) $val;
					if ( in_array( $ival, $phpErrorLevels, true ) ) {
						$phpErrorLevelExpr = (string) $ival;
					}
				}
			} elseif ( is_int( $raw ) && in_array( $raw, $phpErrorLevels, true ) ) {
				$phpErrorLevelExpr = (string) $raw;
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
		$php = [ 'display_errors' => $displayPhpErrors === 'true' ? '1' : '0',
			'log_errors' => $logPhpErrors === 'true' ? '1' : '0',
			'error_reporting' => defined($phpErrorLevelExpr) ? constant($phpErrorLevelExpr) : (int) $phpErrorLevelExpr ];
		foreach ( [ 'memory_limit' => $phpMemoryLimit, 'max_execution_time' => $phpMaxExecution,
			'max_input_vars' => $phpMaxInputVars, 'upload_max_filesize' => $phpUploadMaxFile,
			'post_max_size' => $phpPostMaxSize, 'date.timezone' => $phpTimezone ] as $name => $value ) {
			if ( $value !== null ) { $php[$name] = $value; }
		}
		$ini = $php;
		// Preserve the existing optional php.ini controls; log_errors was only set at runtime.
		unset($ini['log_errors']);
		$ini['display_errors'] = $displayPhpErrors === 'true' ? 'On' : 'Off';
		$override = trim((string)($in['error_reporting_value'] ?? ''));
		$ini['error_reporting'] = $override !== '' ? $override : $phpErrorLevelExpr;
		return [ 'settings' => $settings, 'php' => $php, 'profile' => $profile,
			'ini' => $ini, 'iniPath' => trim((string)($in['php_ini_path'] ?? '')) ];
	}
}
