<?php
namespace AMPBoard\Certificates;

use AMPBoard\Apache\CommandRunner;

/** Explicit certificate generation; construction has no filesystem or command effects. */
final class Generator {
	private string $directory;
	private string $source;
	private string $os;
	private CommandRunner $commands;
	private ScriptInstaller $installer;
	public function __construct( string $apachePath, string $source, string $os, CommandRunner $commands, ?ScriptInstaller $installer = null ) {
		$this->directory = rtrim( $apachePath, '/\\' ) . '/crt';
		$this->source = rtrim( $source, '/\\' ); $this->os = $os;
		$this->commands = $commands; $this->installer = $installer ?? new ScriptInstaller();
	}
	/** @return array{success:bool,output:string} */
	public function generate( string $name ): array {
		$domain = Domain::validate( $name );
		if ( ! in_array( $this->os, [ 'Windows', 'Linux', 'Darwin' ], true ) ) { throw new \RuntimeException( 'Unsupported certificate platform.' ); }
		// Reject ambiguous shell paths before creating or updating anything.
		$this->quote( $this->directory );
		if ( ! is_dir( $this->directory ) && ! @mkdir( $this->directory, 0775, true ) && ! is_dir( $this->directory ) ) {
			throw new \RuntimeException( 'Cannot create certificate directory.' );
		}
		// The existing scripts share cert.conf and cert.log; serialize cooperating requests.
		$lock = @fopen( $this->directory . '/.ampboard-cert.lock', 'c+b' );
		if ( $lock === false ) { throw new \RuntimeException( 'Cannot open certificate generation lock.' ); }
		try {
			if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) { throw new \RuntimeException( 'Certificate generation is already running.' ); }
			$variants = $this->os === 'Windows'
				? [ 'make-cert-silent.ps1', 'make-cert-silent.bat', 'make-cert-prompt.ps1', 'make-cert-prompt.bat' ]
				: [ 'make-cert-silent.sh', 'make-cert-prompt.sh' ];
			$this->installer->install( $this->source, $this->directory, $variants );
			if ( $this->os === 'Windows' ) {
				if ( is_file( $this->directory . '/make-cert-silent.ps1' ) ) {
					$command = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -File ' . $this->quote( $this->directory . '/make-cert-silent.ps1' ) . ' ' . $this->quote( $domain );
				} elseif ( is_file( $this->directory . '/make-cert-silent.bat' ) ) {
					$command = 'cmd /d /s /c "' . $this->quote( $this->directory . '/make-cert-silent.bat' ) . ' ' . $this->quote( $domain ) . '"';
				} else { throw new \RuntimeException( 'Cannot find PowerShell or BAT script in: ' . $this->directory ); }
			} else {
				$script = $this->directory . '/make-cert-silent.sh';
				if ( ! is_file( $script ) ) { throw new \RuntimeException( 'Cannot find shell script in: ' . $this->directory ); }
				$command = 'bash ' . $this->quote( $script ) . ' ' . $this->quote( $domain );
			}
			$result = $this->commands->run( $command );
			return [ 'success' => $result['success'], 'output' => $result['output'] !== '' ? $result['output'] : ( $result['success'] ? 'Certificate generation completed.' : 'Certificate generation failed.' ) ];
		} finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
	}
	private function quote( string $value ): string {
		if ( str_contains( $value, "\0" ) ) { throw new \RuntimeException( 'Unsupported certificate command path.' ); }
		if ( $this->os === 'Windows' ) {
			if ( strpbrk( $value, "\"%!^&|<>()\r\n" ) !== false ) { throw new \RuntimeException( 'Unsupported certificate command path.' ); }
			return '"' . str_replace( '/', '\\', $value ) . '"';
		}
		// POSIX quoting remains testable on Windows as well as Unix.
		return "'" . str_replace( "'", "'\\''", $value ) . "'";
	}
}
