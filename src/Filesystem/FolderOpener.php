<?php
namespace AMPBoard\Filesystem;

use AMPBoard\System\ProcessLauncher;

/** Opens validated directories through the supplied platform launcher. Construction does no I/O. */
final class FolderOpener {
	private string $platform;
	private ProcessLauncher $launcher;
	private $isDirectory;
	public function __construct( string $platform, ProcessLauncher $launcher, ?callable $isDirectory = null ) {
		$this->platform = $platform;
		$this->launcher = $launcher;
		$this->isDirectory = $isDirectory ?? 'is_dir';
	}
	public function open( string $path ): void {
		if ( $path === '' || preg_match( '/[\x00-\x1f\x7f]/', $path ) ) { $this->invalid(); }
		if ( $this->platform === 'Windows' ) {
			$directory = str_replace( '/', '\\', $path );
			$drive = preg_match( '/^[a-z]:\\\\/i', $directory ) === 1;
			$parts = explode( '\\', substr( $directory, 2 ) );
			$unc = str_starts_with( $directory, '\\\\' ) && count( $parts ) >= 2 && $parts[0] !== '' && $parts[1] !== ''
				&& ! in_array( $parts[0], [ '.', '?' ], true ) && ! preg_match( '/[\x00-\x20?]/', $parts[0] );
			if ( ( ! $drive && ! $unc ) || strpos( $directory, '"' ) !== false ) { $this->invalid(); }
		} elseif ( in_array( $this->platform, [ 'Darwin', 'Linux' ], true ) ) {
			$directory = $path;
			if ( $directory[0] !== '/' ) { $this->invalid(); }
		} else { throw new \RuntimeException( 'Unsupported OS: ' . $this->platform ); }
		try { $exists = ( $this->isDirectory )( $directory ); }
		catch ( \Throwable $error ) { throw new \RuntimeException( 'Failed to open folder.', 0, $error ); }
		if ( ! $exists ) { $this->invalid(); }
		if ( $this->platform === 'Windows' ) {
			// Encode path data separately; it is never evaluated as PowerShell source or cmd.exe text.
			// Start-Process accepts one command-line string: quote spaces and double trailing backslashes.
			$trailing = strlen( $directory ) - strlen( rtrim( $directory, '\\' ) );
			$argument = '"' . $directory . str_repeat( '\\', $trailing ) . '"';
			$script = '$ErrorActionPreference = \'Stop\'; try { $argument = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String(\''
				. base64_encode( $argument ) . '\')); Start-Process -FilePath \'explorer.exe\' -ArgumentList $argument; exit 0 } catch { exit 1 }';
			// The script is ASCII, so UTF-16LE encoding needs no optional PHP extension.
			$encoded = base64_encode( implode( "\0", str_split( $script ) ) . "\0" );
			$arguments = [ 'powershell.exe', '-NoProfile', '-NonInteractive', '-EncodedCommand', $encoded ];
		} else { $arguments = [ $this->platform === 'Darwin' ? 'open' : 'xdg-open', $directory ]; }
		try { $started = $this->launcher->launch( $arguments ); }
		catch ( \Throwable $error ) { throw new \RuntimeException( 'Failed to open folder.', 0, $error ); }
		if ( ! $started ) { throw new \RuntimeException( 'Failed to open folder.' ); }
	}
	private function invalid(): void { throw new \InvalidArgumentException( 'Invalid or missing folder path.' ); }
}
