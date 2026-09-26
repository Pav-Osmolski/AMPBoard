<?php
namespace AMPBoard\Export;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveCallbackFilterIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Shared selection for PHP and external archives. Excluded directories are not traversed. */
final class FileSelection {
	public function select( string $source, array $excludes, string $uploadsMode ): array {
		$isWp = FolderCatalog::isWordPress( $source );
		if ( $uploadsMode === 'only' ) {
			if ( is_link( $source . '/wp-content' ) || is_link( $source . '/wp-content/uploads' ) ) { throw new RuntimeException( 'Uploads folder is a symbolic link.' ); }
			if ( ! $isWp || ! FolderCatalog::hasUploads( $source ) ) { throw new RuntimeException( 'Uploads folder not found for this selection.' ); }
			$source .= '/wp-content/uploads';
			$excludes = [];
		}
		if ( ! is_dir( $source ) || is_link( $source ) ) { throw new RuntimeException( 'Source folder does not exist or is a symbolic link.' ); }
		$source = realpath( $source );
		$excluded = [];
		foreach ( $excludes as $name ) {
			$name = strtolower( str_replace( [ '/', '\\' ], '', trim( (string) $name ) ) );
			if ( $name !== '' ) { $excluded[$name] = true; }
		}
		$length = strlen( $source ) + 1;
		$filter = new RecursiveCallbackFilterIterator( new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
			static function ( $file ) use ( $excluded, $isWp, $uploadsMode, $length ): bool {
				if ( $file->isLink() || isset( $excluded[strtolower( $file->getFilename() )] ) ) { return false; }
				$relative = str_replace( '\\', '/', substr( $file->getPathname(), $length ) );
				return ! ( $isWp && $uploadsMode === 'exclude' && $relative === 'wp-content/uploads' );
			} );
		$entries = [];
		foreach ( new RecursiveIteratorIterator( $filter, RecursiveIteratorIterator::SELF_FIRST ) as $file ) {
			$entries[] = [ 'path' => $file->getPathname(), 'rel' => str_replace( '\\', '/', substr( $file->getPathname(), $length ) ), 'isDir' => $file->isDir() ];
		}
		return [ 'source' => $source, 'entries' => $entries ];
	}
}
