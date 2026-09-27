<?php
namespace AMPBoard\Filesystem;

/** Historical slash and trailing-separator normalisation. */
final class Path {
	public static function normalise( string $path ): string {
		$path = str_replace( [ '/', '\\' ], DIRECTORY_SEPARATOR, $path );

		return rtrim( $path, DIRECTORY_SEPARATOR );
	}

}
