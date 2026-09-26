<?php
namespace AMPBoard\Php;

/** Normalizes the existing PHP-manager fields without reading process state. */
final class SettingsInput {
	public function normalise( array $in ): array {
		foreach ( $in as $value ) {
			if ( ! is_scalar( $value ) && $value !== null ) { throw new \InvalidArgumentException( 'PHP settings fields must be scalar.' ); }
		}
		$truthy = [ '1', 1, true, 'true', 'on', 'yes' ];
		$displayPhpErrors = in_array( $in['displayPhpErrors'] ?? null, $truthy, true ) ? 'true' : 'false';
		$logPhpErrors = in_array( $in['logPhpErrors'] ?? null, $truthy, true ) ? 'true' : 'false';
		/* ------------------------------------------------------------------ */
		/* PHP runtime / limits normalisation                                 */
		/* ------------------------------------------------------------------ */

		/* memory_limit: 256M, 1G, or -1 for unlimited */
		$phpMemoryLimit = isset( $in['phpMemoryLimit'] ) && is_string( $in['phpMemoryLimit'] )
			? self::size( $in['phpMemoryLimit'], true, true )
			: null;

		/* max_execution_time: integer seconds, or -1 for unlimited */
		$phpMaxExecution = self::integer( $in['phpMaxExecution'] ?? null, true );

		/* max_input_vars: positive integer */
		$phpMaxInputVars = self::integer( $in['phpMaxInputVars'] ?? null, false );

		/* upload_max_filesize: size string (e.g. 20M, 50M), unit optional */
		$phpUploadMaxFile = isset( $in['phpUploadMaxFile'] ) && is_string( $in['phpUploadMaxFile'] )
			? self::size( $in['phpUploadMaxFile'], false, false )
			: null;

		/* post_max_size: size string (e.g. 20M, 50M), unit optional */
		$phpPostMaxSize = isset( $in['phpPostMaxSize'] ) && is_string( $in['phpPostMaxSize'] )
			? self::size( $in['phpPostMaxSize'], false, false )
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
		if ( strpbrk( $override, "\r\n\0" ) !== false ) { throw new \InvalidArgumentException( 'Invalid error reporting override.' ); }
		$ini['error_reporting'] = $override !== '' ? $override : $phpErrorLevelExpr;
		return [ 'php' => $php, 'ini' => $ini ];
	}

	public static function integer( $raw, bool $allowMinusOne = false ): ?int {
		if ( $raw === null || $raw === '' ) {
			return null;
		}

		if ( is_string( $raw ) ) {
			$raw = trim( $raw );
		}

		if ( ! is_numeric( $raw ) ) {
			return null;
		}

		$ival = (int) $raw;

		if ( $ival > 0 ) {
			return $ival;
		}

		if ( $allowMinusOne && $ival === -1 ) {
			return -1;
		}

		return null;
	}

	/**
	 * Normalise a size-based php.ini option such as "256M", "1G", or "50K".
	 *
	 * @param string|null $raw Raw user input
	 * @param bool $allowMinusOne Whether "-1" is accepted (for memory_limit)
	 * @param bool $requireUnit Whether a K/M/G suffix must be present
	 * @return string|null Normalised uppercase value or null
	 */
	public static function size( ?string $raw, bool $allowMinusOne = false, bool $requireUnit = false ): ?string {
		if ( $raw === null ) {
			return null;
		}

		$val = trim( $raw );

		if ( $val === '' ) {
			return null;
		}

		if ( $allowMinusOne && $val === '-1' ) {
			return '-1';
		}

		$unitPattern = $requireUnit ? '(K|M|G)' : '(K|M|G)?';

		if ( preg_match( '/^[1-9]\d*' . $unitPattern . '$/i', $val ) ) {
			return strtoupper( $val );
		}

		return null;
	}

}
