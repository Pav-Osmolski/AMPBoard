<?php

namespace AMPBoard\Config;

/** Applies profile directives separately from reading data or publishing constants. */
final class PhpSettings {
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

	/** Optional php.ini updates remain best-effort and do not undo a saved profile. */
	public function patch( string $path, array $values ): bool {
		if ( $path === '' || ! is_file( $path ) || ! is_readable( $path ) || ! is_writable( $path ) ) { return false; }
		$content = file_get_contents( $path );
		if ( $content === false ) { return false; }
		foreach ( $values as $name => $value ) {
			if ( ! in_array( $name, ProfileSchema::INI, true ) || $value === null ) { continue; }
			$pattern = '/^\s*' . preg_quote( $name, '/' ) . '\s*=.*/mi';
			$line = $name . ' = ' . $value;
			$content = preg_match( $pattern, $content ) ? preg_replace_callback( $pattern, static function () use ( $line ) { return $line; }, $content ) : $content . "\n" . $line;
		}
		return @file_put_contents( $path, $content, LOCK_EX ) === strlen( $content );
	}
}
