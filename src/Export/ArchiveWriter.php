<?php
namespace AMPBoard\Export;

use RuntimeException;
use Throwable;
use ZipArchive;
use PharData;
use Phar;

/** PHP archive backend. Returns the actual ZIP/TAR.GZ/TAR path or throws. */
final class ArchiveWriter {
	private bool $preferZip;
	private bool $gzip;
	public function __construct( bool $preferZip = true, bool $gzip = true ) { $this->preferZip = $preferZip; $this->gzip = $gzip; }

	public function create( array $entries, string $destination ): string {
		$tarPath = preg_replace( '/\.zip$/i', '.tar', $destination );
		$gzipPath = $tarPath . '.gz';
		try {
			if ( $this->preferZip && class_exists( ZipArchive::class ) ) {
				$zip = new ZipArchive();
				$code = $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE );
				if ( $code !== true ) { throw new RuntimeException( 'ZipArchive open failed (' . $code . ').' ); }
				try {
					foreach ( $entries as $entry ) {
						$added = $entry['isDir'] ? $zip->addEmptyDir( $entry['rel'] ) : $zip->addFile( $entry['path'], $entry['rel'] );
						if ( ! $added ) { throw new RuntimeException( 'Failed to add archive entry.' ); }
					}
				} finally { $closed = $zip->close(); }
				if ( ! $closed || ! is_file( $destination ) ) { throw new RuntimeException( 'ZIP was not created or could not be completed.' ); }
				return $destination;
			}
			if ( ! class_exists( PharData::class ) ) { throw new RuntimeException( 'Neither ZipArchive nor Phar are available.' ); }
			$tar = new PharData( $tarPath );
			foreach ( $entries as $entry ) {
				if ( $entry['isDir'] ) { $tar->addEmptyDir( $entry['rel'] ); }
				else { $tar->addFile( $entry['path'], $entry['rel'] ); }
			}
			$compress = $this->gzip && extension_loaded( 'zlib' );
			if ( $compress ) { $tar->compress( Phar::GZ ); }
			unset( $tar );
			$actual = $compress ? $gzipPath : $tarPath;
			if ( ! is_file( $actual ) ) { throw new RuntimeException( 'TAR archive was not created.' ); }
			if ( $actual !== $tarPath ) { @unlink( $tarPath ); }
			return $actual;
		} catch ( Throwable $error ) {
			unset( $tar, $zip );
			foreach ( array_unique( [ $destination, $tarPath, $gzipPath ] ) as $file ) { if ( is_file( $file ) ) { @unlink( $file ); } }
			throw $error;
		}
	}
}
