<?php
namespace AMPBoard\Filesystem;

/** Tolerant associative JSON reads with per-instance read and diagnostic callbacks. */
final class JsonReader {
	private \Closure $read;
	private \Closure $log;

	public function __construct( ?callable $read = null, ?callable $log = null ) {
		$this->read = \Closure::fromCallable( $read ?? static function ( string $path ) {
			if ( ! is_file( $path ) || ! is_readable( $path ) ) { return false; }
			return @file_get_contents( $path );
		} );
		$this->log = \Closure::fromCallable( $log ?? static function ( string $message ): void { error_log( $message ); } );
	}

	public function readArray( string $path ): array {
		$raw = ( $this->read )( $path );
		if ( $raw === false || $raw === '' ) { return []; }
		$decoded = json_decode( $raw, true );
		if ( is_array( $decoded ) ) { return $decoded; }
		( $this->log )( basename( $path ) . ' JSON decode failed: ' . json_last_error_msg() );
		return [];
	}
}
