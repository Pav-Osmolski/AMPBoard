<?php

namespace AMPBoard\Config;

use RuntimeException;
use Throwable;

/** Stages a profile's files before replacement; rolls back ordinary commit failures. */
class AtomicFileWriter {
	protected function replace( string $from, string $to ): bool { return @rename( $from, $to ); }

	public function write( string $directory, array $files ): void {
		$newDirectory = ! is_dir( $directory );
		$destination = $directory;
		// Publish a first profile as a complete directory, so readers retain the fallback
		// until all four files exist. This also avoids empty directories after failed saves.
		if ( $newDirectory ) { $directory .= '.pending.' . bin2hex( random_bytes( 8 ) ); }
		if ( $newDirectory && ! @mkdir( $directory, 0750, true ) && ! is_dir( $directory ) ) {
			throw new RuntimeException( 'Cannot create profile directory.' );
		}
		$lock = @fopen( $directory . '/.profile.lock', 'c+b' );
		$staged = []; $originals = []; $committed = [];
		$recovery = [];
		try {
			if ( $lock === false ) { throw new RuntimeException( 'Cannot open profile lock.' ); }
			if ( ! flock( $lock, LOCK_EX ) ) { throw new RuntimeException( 'Cannot lock profile.' ); }
			foreach ( $files as $name => $content ) {
				if ( basename( $name ) !== $name ) { throw new RuntimeException( 'Invalid profile filename.' ); }
				$path = $directory . '/' . $name;
				if ( file_exists( $path ) && ! is_file( $path ) ) { throw new RuntimeException( 'Profile destination is not a file.' ); }
				$originals[ $path ] = is_file( $path ) ? file_get_contents( $path ) : null;
				if ( $originals[ $path ] === false ) { throw new RuntimeException( 'Cannot read previous profile.' ); }
				$temp = $path . '.tmp.' . bin2hex( random_bytes( 8 ) );
				$staged[ $path ] = $temp;
				if ( @file_put_contents( $temp, $content, LOCK_EX ) !== strlen( $content ) ) {
					throw new RuntimeException( 'Cannot stage profile file.' );
				}
				@chmod( $temp, 0600 );
			}
			foreach ( $staged as $path => $temp ) {
				if ( ! $this->replace( $temp, $path ) ) { throw new RuntimeException( 'Cannot replace profile file.' ); }
				$committed[] = $path;
				if ( function_exists( 'opcache_invalidate' ) ) { @opcache_invalidate( $path, true ); }
			}
			if ( $newDirectory ) {
				// Windows cannot rename a directory containing an open lock file.
				fclose( $lock );
				$lock = null;
				if ( ! @rename( $directory, $destination ) ) { throw new RuntimeException( 'Cannot publish profile directory.' ); }
			}
		} catch ( Throwable $error ) {
			foreach ( array_reverse( $committed ) as $path ) {
				$old = $originals[ $path ];
				if ( $old === null ) { $restored = @unlink( $path ); }
				else {
					$temp = $staged[ $path ];
					$restored = @file_put_contents( $temp, $old, LOCK_EX ) === strlen( $old );
					@chmod( $temp, 0600 );
					$restored = $restored && @rename( $temp, $path );
				}
				if ( ! $restored ) { $recovery[] = $path; }
				if ( function_exists( 'opcache_invalidate' ) ) { @opcache_invalidate( $path, true ); }
			}
			if ( $recovery ) { throw new RuntimeException( 'Profile rollback failed; retained temporary recovery files. Restore the profile from backup if necessary.', 0, $error ); }
			throw $error;
		} finally {
			foreach ( $staged as $path => $temp ) { if ( ! in_array( $path, $recovery, true ) && is_file( $temp ) ) { @unlink( $temp ); } }
			if ( is_resource( $lock ) ) { fclose( $lock ); }
			// An unsuccessful first save must not leave an empty profile shadowing defaults.
			if ( $newDirectory && is_dir( $directory ) ) {
				@unlink( $directory . '/.profile.lock' );
				@rmdir( $directory );
			}
		}
	}
}
