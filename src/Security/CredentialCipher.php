<?php

namespace AMPBoard\Security;

use RuntimeException;

/** Existing AES-256-CBC wire format, with an explicitly supplied key file. */
final class CredentialCipher {
	private string $keyFile;

	public function __construct( string $keyFile ) {
		$this->keyFile = $keyFile;
	}

	public function key( bool $create = false ): string {
		// Exclusive creation prevents concurrent first saves from replacing each other's key.
		if ( ! is_file( $this->keyFile ) && $create ) {
			$handle = @fopen( $this->keyFile, 'x+b' );
			if ( $handle !== false ) {
				try {
					if ( ! flock( $handle, LOCK_EX ) ) { throw new RuntimeException( 'Cannot lock encryption key.' ); }
					$hex = bin2hex( random_bytes( 32 ) );
					if ( fwrite( $handle, $hex ) !== 64 || ! fflush( $handle ) ) {
						throw new RuntimeException( 'Cannot persist encryption key.' );
					}
					@chmod( $this->keyFile, 0600 );
				} finally { fclose( $handle ); }
			}
		}
		$handle = @fopen( $this->keyFile, 'rb' );
		if ( $handle === false ) { throw new RuntimeException( 'Encryption key is missing or unreadable.' ); }
		try {
			if ( ! flock( $handle, LOCK_SH ) ) { throw new RuntimeException( 'Cannot read encryption key.' ); }
			$raw = trim( (string) stream_get_contents( $handle ) );
		} finally { fclose( $handle ); }
		if ( ! preg_match( '/^[a-f0-9]{64}$/iD', $raw ) ) {
			throw new RuntimeException( 'Invalid encryption key; restore the existing key rather than replacing it.' );
		}
		return hex2bin( $raw );
	}

	public function encrypt( string $value ): string {
		$iv = random_bytes( 16 );
		$encrypted = openssl_encrypt( $value, 'AES-256-CBC', $this->key( true ), OPENSSL_RAW_DATA, $iv );
		if ( $encrypted === false ) { throw new RuntimeException( 'Credential encryption failed.' ); }
		return base64_encode( $iv . $encrypted );
	}

	public function decrypt( string $value ): string {
		$data = base64_decode( $value, true );
		if ( $data === false || strlen( $data ) < 32 || ( strlen( $data ) - 16 ) % 16 !== 0 ) {
			throw new RuntimeException( 'Invalid encrypted credential.' );
		}
		$plain = @openssl_decrypt( substr( $data, 16 ), 'AES-256-CBC', $this->key(), OPENSSL_RAW_DATA, substr( $data, 0, 16 ) );
		if ( $plain === false ) { throw new RuntimeException( 'Credential decryption failed.' ); }
		return $plain;
	}

	/** Legacy profiles have no encryption marker, so retain their plaintext fallback. */
	public function resolveLegacy( string $value, bool $allowFallback = true ): string {
		if ( strlen( $value ) < 24 || base64_decode( $value, true ) === false ) {
			return $allowFallback ? $value : '';
		}
		try { $plain = $this->decrypt( $value ); }
		catch ( RuntimeException $e ) { return $allowFallback ? $value : ''; }
		return $plain !== '' ? $plain : ( $allowFallback ? $value : '' );
	}
}
