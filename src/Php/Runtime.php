<?php
namespace AMPBoard\Php;

use AMPBoard\Config\ProfileSchema;

/** The boundary for process-wide PHP directives and runtime inspection. */
final class Runtime {
	public function apply( array $values ): void {
		foreach ( $values as $name => $value ) {
			if ( ! in_array( $name, ProfileSchema::INI, true ) ) { continue; }
			if ( $name === 'error_reporting' ) { error_reporting( (int) $value ); }
			else {
				ini_set( $name, (string) $value );
				if ( $name === 'date.timezone' ) { date_default_timezone_set( (string) $value ); }
			}
		}
	}

	public function values(): array {
		$values = [];
		foreach ( ProfileSchema::INI as $name ) { $values[$name] = ini_get( $name ); }
		return $values;
	}

	public function inspect(): array {
		return [ 'version' => phpversion(), 'threadSafe' => (bool) ZEND_THREAD_SAFE, 'sapi' => PHP_SAPI,
			'loadedIni' => php_ini_loaded_file() ?: '', 'scannedIni' => php_ini_scanned_files() ?: '' ];
	}
}
