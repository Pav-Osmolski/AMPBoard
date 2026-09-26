<?php
namespace AMPBoard\Export;

use RuntimeException;
use Throwable;

/** Orchestrates exports in a private workspace; publishes only a completed archive. */
final class Workflow {
	private FolderCatalog $folders;
	private DatabaseExporter $database;
	private ArchiveWriter $archives;
	private ExternalArchiver $external;
	private array $excludes;
	private string $destination;
	private string $publicPath;
	private string $temporaryPath;

	public function __construct( FolderCatalog $folders, DatabaseExporter $database, ArchiveWriter $archives, ExternalArchiver $external,
		array $excludes, string $destination, string $publicPath, string $temporaryPath ) {
		$this->folders = $folders; $this->database = $database; $this->archives = $archives; $this->external = $external;
		$this->excludes = $excludes; $this->destination = $destination; $this->publicPath = rtrim( $publicPath, '/' ); $this->temporaryPath = $temporaryPath;
	}

	public function scan(): array { return $this->folders->scan(); }
	public function databases(): array { return $this->database->databases(); }

	public function files( int $group, string $folder, string $uploadsMode, string $engine ): array {
		$folder = trim( $folder );
		$source = $this->folders->select( $group, $folder );
		$mode = in_array( $uploadsMode, [ 'include', 'only' ], true ) ? $uploadsMode : 'exclude';
		$selection = ( new FileSelection() )->select( $source, $this->excludes, $mode );
		$prefix = 'files-' . $this->safeName( $folder ) . '-' . ( $mode === 'only' ? 'uploads-' : '' );
		return $this->workspace( $prefix, function ( string $directory, string $name ) use ( $selection, $engine ): array {
			return $this->archive( $selection['entries'], $selection['source'], $directory . '/' . $name . '.zip', $engine );
		} );
	}

	public function dump( string $database, string $engine ): array {
		$database = trim( $database );
		if ( $database === '' ) { throw new RuntimeException( 'No database selected.' ); }
		return $this->workspace( 'db-' . $this->safeName( $database ) . '-', function ( string $directory, string $name ) use ( $database, $engine ): array {
			$sql = $this->database->dump( $database );
			$path = $directory . '/' . $name . '.sql';
			if ( file_put_contents( $path, $sql ) !== strlen( $sql ) ) { throw new RuntimeException( 'Failed to write SQL dump.' ); }
			return $this->archive( [ [ 'path' => $path, 'rel' => basename( $path ), 'isDir' => false ] ], $directory, $directory . '/' . $name . '.zip', $engine );
		} );
	}

	private function archive( array $entries, string $source, string $destination, string $engine ): array {
		$notice = null;
		if ( $engine === 'external' ) {
			try { return [ $this->external->create( $entries, $source, $destination ), null ]; }
			catch ( Throwable $error ) {
				if ( is_file( $destination ) && ! unlink( $destination ) ) { throw new RuntimeException( 'Cannot remove incomplete external archive.' ); }
				$notice = $error->getMessage() . ' Falling back to PHP ZipArchive.';
			}
		}
		return [ $this->archives->create( $entries, $destination ), $notice ];
	}

	private function safeName( string $name ): string { return preg_replace( '/[^a-zA-Z0-9._-]+/', '_', $name ); }

	private function workspace( string $prefix, callable $build ): array {
		$id = bin2hex( random_bytes( 8 ) );
		$directory = rtrim( $this->temporaryPath, '/\\' ) . '/ampboard-export-' . $id;
		if ( ! mkdir( $directory, 0700 ) ) { throw new RuntimeException( 'Cannot create temporary export directory.' ); }
		$staged = null;
		try {
			$name = $prefix . date( 'Ymd-His' ) . '-' . $id;
			[ $archive, $notice ] = $build( $directory, $name );
			if ( ! is_dir( $this->destination ) && ! @mkdir( $this->destination, 0775, true ) && ! is_dir( $this->destination ) ) { throw new RuntimeException( 'Cannot create exports directory.' ); }
			$name = basename( $archive );
			$final = rtrim( $this->destination, '/\\' ) . '/' . $name;
			$staged = $final . '.part';
			if ( ! @copy( $archive, $staged ) || filesize( $staged ) !== filesize( $archive ) || ! @rename( $staged, $final ) ) { throw new RuntimeException( 'Cannot publish completed export.' ); }
			return [ 'ok' => true, 'href' => $this->publicPath . '/' . $name, 'name' => $name, 'message' => $notice ];
		} finally {
			if ( $staged !== null && is_file( $staged ) ) { @unlink( $staged ); }
			foreach ( glob( $directory . '/*' ) ?: [] as $file ) { if ( is_file( $file ) ) { @unlink( $file ); } }
			@rmdir( $directory );
		}
	}
}
