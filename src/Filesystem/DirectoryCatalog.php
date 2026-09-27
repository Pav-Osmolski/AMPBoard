<?php
namespace AMPBoard\Filesystem;

/** Dashboard folder discovery with an explicit document root. Symlinks retain their historical behaviour. */
final class DirectoryCatalog {
	private string $root;
	public function __construct( string $root ) { $this->root = $root; }
	public function resolve( string $relative ): array {
		$relative = (string) $relative;
		$subdir   = trim( Path::normalise( $relative ), DIRECTORY_SEPARATOR );
		if ( strpos( $subdir, '..' ) !== false ) {
			return [ 'dir' => '', 'error' => 'Security: directory traversal detected in "dir".' ];
		}
		$abs = rtrim( $this->root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR;

		return [ 'dir' => $abs, 'error' => null ];
	}

	/**
	 * List immediate subdirectories of a directory, skipping dot entries and sorting naturally.
	 *
	 * @param string $absDir
	 *
	 * @return array<int, string> Folder basenames
	 */
	public function listDirectories( string $absDir ): array {
		if ( ! is_dir( $absDir ) ) {
			return [];
		}
		$out = [];
		try { $it = new \DirectoryIterator( $absDir ); }
		catch ( \UnexpectedValueException $error ) { return []; }
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
}
