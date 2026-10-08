<?php
namespace AMPBoard\Apache;

/** Keeps native PHP/environment reads lazy and outside inspector policy. */
final class NativeRuntimeReader implements RuntimeReader {
	public function isApache(): bool {
		return php_sapi_name() === 'apache2handler' || function_exists( 'apache_get_version' );
	}
	public function apacheVersion(): ?string {
		return function_exists( 'apache_get_version' ) ? (string) apache_get_version() : null;
	}
	public function moduleInfo(): string {
		ob_start();
		try { phpinfo( INFO_MODULES ); return (string) ob_get_contents(); }
		finally { ob_end_clean(); }
	}
	public function environment(): array {
		$values = [];
		foreach ( [ 'APACHE_RUN_DIR', 'APACHE_PID_FILE', 'APACHE_LOCK_DIR', 'INVOCATION_ID' ] as $name ) {
			$value = getenv( $name );
			if ( $value ) { $values[$name] = $value; }
		}
		return $values;
	}
	public function iniFiles(): array {
		return [ 'Loaded php.ini' => php_ini_loaded_file() ?: 'N/A', 'Scanned .ini files' => php_ini_scanned_files() ?: 'N/A' ];
	}
}
