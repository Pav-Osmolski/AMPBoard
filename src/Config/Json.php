<?php
namespace AMPBoard\Config;

/** Existing associative JSON normalization and profile serialization policy. */
final class Json {
	public static function canonicalise( string $raw, bool $allowEmptyArray = true ): string {
		$raw = trim( $raw );
		if ( $raw === '' ) { return $allowEmptyArray ? '[]' : '{}'; }
		$data = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) ) { throw new \InvalidArgumentException( 'JSON root must be array or object.' ); }
		return self::encode( $data );
	}

	public static function encode( mixed $value ): string {
		return json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
	}
}
