<?php
namespace AMPBoard\Certificates;

/** Updates only the known bundled scripts, staging each replacement beside its target. */
class ScriptInstaller {
	public function install( string $sourceDirectory, string $targetDirectory, array $names ): void {
		foreach ( $names as $name ) {
			$source = $sourceDirectory . '/' . $name; $target = $targetDirectory . '/' . $name;
			if ( ! is_file( $source ) ) { continue; }
			if ( file_exists( $target ) && ! is_file( $target ) ) { throw new \RuntimeException( 'Certificate script target is not a file.' ); }
			$sourceTime = @filemtime( $source );
			$targetTime = is_file( $target ) ? @filemtime( $target ) : null;
			if ( $sourceTime === false || $targetTime === false ) { throw new \RuntimeException( 'Cannot inspect certificate scripts.' ); }
			if ( $targetTime !== null && $sourceTime <= $targetTime ) { continue; }
			$stage = $targetDirectory . '/.ampboard-script-' . bin2hex( random_bytes( 8 ) ) . '.tmp';
			try {
				if ( ! $this->copy( $source, $stage ) || ! $this->replace( $stage, $target ) ) {
					throw new \RuntimeException( 'Cannot update certificate script: ' . $name );
				}
			} finally { if ( is_file( $stage ) ) { @unlink( $stage ); } }
		}
	}
	protected function copy( string $source, string $target ): bool { return @copy( $source, $target ); }
	protected function replace( string $source, string $target ): bool { return @rename( $source, $target ); }
}
