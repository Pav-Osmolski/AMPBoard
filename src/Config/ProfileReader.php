<?php

namespace AMPBoard\Config;

use UnexpectedValueException;

/** Reads trusted local PHP; returned arrays have no constant or INI side effects. */
final class ProfileReader {
	public function read( string $path, array $initial = [] ): array {
		if ( ! is_file( $path ) ) { return [ 'settings' => [], 'php' => [], 'legacy' => false ]; }
		[ $returned, $variables ] = ( static function ( string $file, array $initial ): array {
			extract( array_intersect_key( $initial, array_flip( array_merge( ProfileSchema::FLAGS, [ 'theme' ] ) ) ), EXTR_SKIP );
			$returned = require $file;
			return [ $returned, get_defined_vars() ];
		} )( $path, $initial );
		if ( is_array( $returned ) ) {
			if ( ( $returned['version'] ?? null ) !== 1 || ! is_array( $returned['settings'] ?? null )
				|| ! is_array( $returned['php'] ?? [] ) ) {
				throw new UnexpectedValueException( 'Unsupported profile format.' );
			}
			$settings = $returned['settings'];
			foreach ( $settings as $name => $value ) {
				if ( ! array_key_exists( $name, ProfileSchema::defaults() ) ) { throw new UnexpectedValueException( 'Unknown profile setting.' ); }
				$credential = in_array( $name, [ 'DB_USER', 'DB_PASSWORD' ], true );
				$valid = $name === 'EXPORT_EXCLUDE' ? is_array( $value ) && count( array_filter( $value, 'is_string' ) ) === count( $value )
					: ( in_array( $name, array_merge( ProfileSchema::FLAGS, [ 'DEMO_MODE' ] ), true ) ? is_bool( $value )
					: is_string( $value ) || ( $credential && is_array( $value ) && array_keys( $value ) === [ 'encrypted' ] && is_string( $value['encrypted'] ) ) );
				if ( ! $valid ) { throw new UnexpectedValueException( 'Invalid profile setting type.' ); }
			}
			foreach ( $returned['php'] ?? [] as $name => $value ) {
				if ( ! in_array( $name, ProfileSchema::INI, true ) || ! is_scalar( $value ) ) {
					throw new UnexpectedValueException( 'Invalid profile PHP directive.' );
				}
			}
			return [ 'settings' => $settings, 'php' => $returned['php'] ?? [], 'legacy' => false ];
		}
		if ( $returned !== 1 && $returned !== null ) { throw new UnexpectedValueException( 'Invalid legacy profile.' ); }
		$settings = array_intersect_key( $variables, array_flip( array_merge( ProfileSchema::FLAGS, [ 'theme' ] ) ) );
		return [ 'settings' => array_merge( $settings, LegacyConstants::read() ), 'php' => [], 'legacy' => true ];
	}
}
