<?php
namespace AMPBoard\Filesystem;

/** Stable profile directory naming; changing this can select another profile. */
final class FolderName {
	public static function sanitise( string $name ): string {
		// Try to convert accented or symbolic characters to ASCII.
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $name );
			if ( is_string( $converted ) && $converted !== '' ) {
				$name = $converted;
			}
		}

		// Replace anything not in our allowlist.
		$name = preg_replace( '/[^A-Za-z0-9._-]/', '_', $name );

		// Remove leading/trailing punctuation to avoid odd paths.
		$name = trim( (string) $name, '._-' );

		// If empty, fall back to something predictable.
		if ( $name === '' ) {
			$name = 'user';
		}

		// Avoid Windows reserved names for cross-platform support.
		$reserved = [
			'CON',
			'PRN',
			'AUX',
			'NUL',
			'COM1',
			'COM2',
			'COM3',
			'COM4',
			'COM5',
			'COM6',
			'COM7',
			'COM8',
			'COM9',
			'LPT1',
			'LPT2',
			'LPT3',
			'LPT4',
			'LPT5',
			'LPT6',
			'LPT7',
			'LPT8',
			'LPT9',
		];

		if ( in_array( strtoupper( $name ), $reserved, true ) ) {
			$name = 'user_' . $name;
		}

		return $name;
	}

}
