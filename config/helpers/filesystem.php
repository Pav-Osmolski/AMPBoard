<?php
/**
 * Filesystem helpers
 *
 * normalise_subdir(), list_subdirs(), sanitizeFolderName()
 *
 * @author  Pawel Osmolski
 * @version 1.1
 */

/**
 * Normalise a configured subdirectory safely below HTDOCS_PATH.
 *
 * Uses normalise_path() for slash handling, prevents traversal,
 * and anchors the result under HTDOCS_PATH.
 *
 * @param string $relative
 *
 * @return array{dir:string,error:?string}
 */
function normalise_subdir( string $relative ): array {
	$relative = (string) $relative;
	$subdir   = trim( normalise_path( $relative ), DIRECTORY_SEPARATOR );
	if ( strpos( $subdir, '..' ) !== false ) {
		return [ 'dir' => '', 'error' => 'Security: directory traversal detected in "dir".' ];
	}
	$abs = rtrim( HTDOCS_PATH, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR;

	return [ 'dir' => $abs, 'error' => null ];
}

/**
 * List immediate subdirectories of a directory, skipping dot entries and sorting naturally.
 *
 * @param string $absDir
 *
 * @return array<int, string> Folder basenames
 */
function list_subdirs( string $absDir ): array {
	if ( ! is_dir( $absDir ) ) {
		return [];
	}
	$out = [];
	$it  = new DirectoryIterator( $absDir );
	foreach ( $it as $f ) {
		if ( $f->isDot() ) {
			continue;
		}
		if ( $f->isDir() ) {
			$out[] = $f->getBasename();
		}
	}
	natcasesort( $out );

	return array_values( $out );
}

/**
 * Convert a string into a filesystem-safe folder name.
 *
 * This keeps only A-Z, a-z, 0-9, dot, underscore, and hyphen.
 * Everything else becomes an underscore. Leading/trailing junk is trimmed.
 * Includes ASCII transliteration where possible and avoids empty results.
 *
 * @param string $name Original string (e.g. username).
 *
 * @return string Sanitised folder-safe version.
 */
function sanitizeFolderName( string $name ): string {
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
