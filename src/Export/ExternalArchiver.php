<?php
namespace AMPBoard\Export;

use RuntimeException;

/** Discovers trusted archivers in supplied directories; consumes the shared file selection. */
final class ExternalArchiver {
	private ProcessRunner $runner;
	private array $directories;
	public function __construct( ProcessRunner $runner, array $directories ) { $this->runner = $runner; $this->directories = $directories; }

	private function find( array $names ): ?string {
		foreach ( $names as $name ) {
			foreach ( $this->directories as $directory ) {
				if ( $directory === '' ) { continue; }
				$path = rtrim( trim( $directory, '"' ), '/\\' ) . '/' . $name;
				if ( is_file( $path ) && is_executable( $path ) ) { return realpath( $path ) ?: $path; }
			}
		}
		return null;
	}

	public function create( array $entries, string $source, string $destination ): string {
		$lines = [];
		foreach ( $entries as $entry ) {
			// Only physically empty directories are safe to give 7-Zip: adding a nonempty
			// directory would recursively reintroduce files excluded by the shared selector.
			if ( $entry['isDir'] && count( scandir( $entry['path'] ) ) > 2 ) { continue; }
			if ( strpbrk( $entry['rel'], "\r\n*?[]" ) !== false ) { throw new RuntimeException( 'Filename requires the PHP archive engine.' ); }
			$lines[] = $entry['rel'] . ( $entry['isDir'] ? '/' : '' );
		}
		if ( ! $lines ) { throw new RuntimeException( 'No files selected for the external archiver.' ); }
		$list = implode( "\n", $lines ) . "\n";
		$manifest = $destination . '.list';
		if ( file_put_contents( $manifest, $list ) !== strlen( $list ) ) { throw new RuntimeException( 'Cannot write archive file list.' ); }
		$error = 'No external archiver (7-Zip or zip) found on PATH.';
		try {
			$seven = $this->find( DIRECTORY_SEPARATOR === '\\' ? [ '7z.exe', '7za.exe', '7zz.exe' ] : [ '7z', '7za', '7zz' ] );
			$zip = $this->find( DIRECTORY_SEPARATOR === '\\' ? [ 'zip.exe' ] : [ 'zip' ] );
			foreach ( [ 'seven' => $seven, 'zip' => $zip ] as $kind => $binary ) {
				if ( $binary === null ) { continue; }
				if ( is_file( $destination ) ) { unlink( $destination ); }
				$args = $kind === 'seven' ? [ $binary, 'a', '-tzip', '-scsUTF-8', '-spf', $destination, '-r-', '-sse', '@' . $manifest ] : [ $binary, $destination, '-@' ];
				$result = $this->runner->run( $args, $source, $kind === 'zip' ? $list : '' );
				clearstatcache( true, $destination );
				if ( $result['success'] && is_file( $destination ) && filesize( $destination ) > 0 ) { return $destination; }
				$error = ( $kind === 'seven' ? '7-Zip' : 'System zip' ) . ' failed or produced no archive.';
			}
			throw new RuntimeException( $error );
		} finally {
			@unlink( $manifest );
			// The caller removes any partial archive before its PHP fallback.
		}
	}
}
