<?php
namespace AMPBoard\Php;

use AMPBoard\Config\ProfileSchema;
use Throwable;

/** Optional file edits are separate from runtime directives and profile persistence. */
class IniFile {
	private string $path;
	public function __construct( string $path ) { $this->path = $path; }
	protected function replace( string $from, string $to ): bool { return @rename( $from, $to ); }

	/** Update ordinary sections, retaining per-host/per-path overrides and untouched lines. */
	public function render( string $content, array $values ): string {
		$pending = [];
		foreach ( $values as $name => $value ) {
			if ( ! in_array( $name, ProfileSchema::INI, true ) || $value === null ) { continue; }
			if ( ! is_scalar( $value ) || strpbrk( (string) $value, "\r\n\0" ) !== false ) {
				throw new \InvalidArgumentException( 'INI values must be single-line scalars.' );
			}
			$pending[$name] = (string) $value;
		}
		$missing = $pending;
		if ( ! $pending ) { return $content; }
		$newline = strpos( $content, "\r\n" ) !== false ? "\r\n" : "\n";
		$lines = preg_split( '/(\r\n|\n|\r)/', $content );
		$scoped = false;
		foreach ( $lines as &$line ) {
			if ( preg_match( '/^\s*\[([^\]]+)\]/', $line, $section ) ) {
				$scoped = (bool) preg_match( '/^(PATH|HOST)\s*=/i', trim( $section[1] ) );
			}
			if ( $scoped || ! preg_match( '/^(\s*)([a-zA-Z_][a-zA-Z0-9_.]*)\s*=/', $line, $match ) ) { continue; }
			$name = strtolower( $match[2] );
			if ( ! array_key_exists( $name, $pending ) ) { continue; }
			$line = $match[1] . $match[2] . ' = ' . $pending[$name];
			unset( $missing[$name] );
		}
		unset( $line );
		// Put new global directives before any section rather than inside a PATH/HOST block.
		$prefix = '';
		foreach ( $missing as $name => $value ) { $prefix .= $name . ' = ' . $value . $newline; }
		return $prefix . implode( $newline, $lines );
	}

	/** Best effort: a failed INI edit does not roll back a successfully saved profile. */
	public function patch( array $values ): bool {
		$lock = null; $temp = null;
		try {
			if ( $this->path === '' || ! is_file( $this->path ) || ! is_readable( $this->path ) || ! is_writable( $this->path ) ) { return false; }
			$path = realpath( $this->path );
			// A stable sidecar serializes AMPBoard writers even when the INI is replaced.
			$lock = @fopen( $path . '.ampboard.lock', 'c+b' );
			if ( ! is_resource( $lock ) || ! flock( $lock, LOCK_EX ) ) { return false; }
			$content = @file_get_contents( $path );
			if ( $content === false ) { return false; }
			$updated = $this->render( $content, $values );
			if ( $updated === $content ) { return true; }
			$temp = $path . '.ampboard.' . bin2hex( random_bytes( 8 ) ) . '.tmp';
			if ( @file_put_contents( $temp, $updated, LOCK_EX ) !== strlen( $updated ) ) { return false; }
			$mode = @fileperms( $path );
			if ( $mode === false || ! @chmod( $temp, $mode & 0777 ) ) { return false; }
			return $this->replace( $temp, $path );
		} catch ( Throwable $error ) { return false; }
		finally {
			if ( $temp !== null && is_file( $temp ) ) { @unlink( $temp ); }
			if ( is_resource( $lock ) ) { fclose( $lock ); }
		}
	}
}
