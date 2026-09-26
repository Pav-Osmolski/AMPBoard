<?php
namespace AMPBoard\Export;

use InvalidArgumentException;

/** Resolves the same folder groups used by the export UI. */
final class FolderCatalog {
	private string $htdocs;
	private array $groups;
	public function __construct( string $htdocs, array $groups ) { $this->htdocs = $htdocs; $this->groups = $groups; }

	public function scan(): array {
		$groups = [];
		foreach ( $this->groups as $i => $entry ) {
			if ( empty( $entry['dir'] ) || empty( $entry['title'] ) ) { continue; }
			$folders = self::listSubfolders( $this->htdocs, (string) $entry['dir'],
				is_array( $entry['excludeList'] ?? null ) ? $entry['excludeList'] : [],
				is_array( $entry['urlRules'] ?? null ) ? $entry['urlRules'] : [] );
			$groups[] = [ 'index' => $i, 'title' => (string) $entry['title'], 'dir' => (string) $entry['dir'],
				'subfolders' => array_map( static function ( $folder ) {
					return [ 'name' => $folder['name'], 'isWordPress' => $folder['isWp'], 'hasUploads' => $folder['hasUploads'] ];
				}, $folders ) ];
		}
		return $groups;
	}

	public function select( int $index, string $name ): string {
		if ( $name === '' || strpos( $name, '..' ) !== false || strpbrk( $name, "/\\\0" ) !== false ) { throw new InvalidArgumentException( 'Invalid folder name.' ); }
		if ( $index < 0 ) { throw new InvalidArgumentException( 'Invalid folder selection.' ); }
		if ( ! isset( $this->groups[$index] ) ) { throw new InvalidArgumentException( 'Group not found.' ); }
		$group = $this->groups[$index];
		foreach ( self::listSubfolders( $this->htdocs, (string) ( $group['dir'] ?? '' ),
			is_array( $group['excludeList'] ?? null ) ? $group['excludeList'] : [],
			is_array( $group['urlRules'] ?? null ) ? $group['urlRules'] : [] ) as $folder ) {
			if ( $folder['name'] === $name ) { return realpath( $folder['abs'] ) ?: $folder['abs']; }
		}
		throw new InvalidArgumentException( 'Folder no longer exists or is excluded from this selection.' );
	}

	public static function parseRegex( string $raw ): ?string {
		$raw = trim( $raw );
		if ( strlen( $raw ) >= 2 && $raw[0] === '/' && strrpos( $raw, '/' ) !== 0 ) {
			return $raw;
		}

		return null;
	}

	public static function isWordPress( string $absPath ): bool {
		return is_file( $absPath . DIRECTORY_SEPARATOR . 'wp-config.php' )
		       || is_dir( $absPath . DIRECTORY_SEPARATOR . 'wp-content' );
	}

	public static function hasUploads( string $absPath ): bool {
		return is_dir( $absPath . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads' );
	}

	public static function listSubfolders( string $htdocsPath, string $dirEntry, array $excludeList = [], array $urlRules = [] ): array {
		$result  = [];
		$baseAbs = rtrim( $htdocsPath, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . str_replace( [
				'/',
				'\\'
			], DIRECTORY_SEPARATOR, $dirEntry );
		if ( ! is_dir( $baseAbs ) ) {
			return $result;
		}

		$excludeSet = array_flip( $excludeList );
		$matchRaw   = isset( $urlRules['match'] ) && is_string( $urlRules['match'] ) ? $urlRules['match'] : null;
		$pattern    = $matchRaw ? self::parseRegex( $matchRaw ) : null;

		$items = @scandir( $baseAbs ) ?: [];
		foreach ( $items as $name ) {
			if ( $name === '.' || $name === '..' ) {
				continue;
			}
			if ( isset( $excludeSet[ $name ] ) ) {
				continue;
			}
			$abs = $baseAbs . DIRECTORY_SEPARATOR . $name;
			if ( ! is_dir( $abs ) || is_link( $abs ) ) {
				continue;
			}

			if ( $pattern !== null ) {
				$pm = @preg_match( $pattern, $name );
				if ( $pm !== 1 ) {
					continue;
				}
			}

			$isWp       = self::isWordPress( $abs );
			$hasUploads = $isWp && self::hasUploads( $abs );
			$result[]   = [ 'name' => $name, 'abs' => $abs, 'isWp' => $isWp, 'hasUploads' => $hasUploads ];
		}

		usort( $result, function ( $a, $b ) {
			return strnatcasecmp( $a['name'], $b['name'] );
		} );

		return $result;
	}
}
