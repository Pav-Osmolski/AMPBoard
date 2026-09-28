<?php
namespace AMPBoard\Certificates;

final class Domain {
	public static function validate( string $name ): string {
		if ( $name === '' || strlen( $name ) > 253 ) { throw new \InvalidArgumentException( 'Invalid domain name.' ); }
		foreach ( explode( '.', $name ) as $label ) {
			if ( ! preg_match( '/\A[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\z/', $label ) ) {
				throw new \InvalidArgumentException( 'Invalid domain name.' );
			}
		}
		// The name becomes a directory on Windows, where device names also reserve extensions.
		if ( preg_match( '/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|\z)/i', $name ) ) {
			throw new \InvalidArgumentException( 'Invalid domain name.' );
		}
		return $name;
	}
}
