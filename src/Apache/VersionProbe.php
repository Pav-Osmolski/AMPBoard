<?php
namespace AMPBoard\Apache;

/** Header-only version discovery; paths, platform, server metadata and commands are explicit. */
final class VersionProbe {
	private string $path;
	private string $os;
	private string $software;
	private CommandRunner $commands;
	public function __construct( string $path, string $os, string $software, CommandRunner $commands ) {
		$this->path = $path; $this->os = $os; $this->software = $software; $this->commands = $commands;
	}
	private function output( string $command ): string {
		$result = $this->commands->run( $command );
		return $result['success'] ? $result['output'] : '';
	}
	public function inspect(): array {
		$os = $this->os; $apacheVersion = ''; $apacheBin = '';
		try {
			if ( $this->path !== '' ) {
				$binCandidates = [
					'bin/httpd',
					'bin/httpd.exe',
					'sbin/httpd',
					'httpd',
					'httpd.exe',
					'bin/apachectl',
					'apachectl',
					'sbin/apachectl',
				];
				foreach ( $binCandidates as $subpath ) {
					$testPath = rtrim( $this->path, '/\\' ) . '/' . $subpath;
					if ( is_file( $testPath ) ) {
						$apacheBin = $testPath;
						break;
					}
				}
			}

			if ( empty( $apacheBin ) ) {
				if ( $os === 'Windows' ) {
					$apachePath = trim( (string) ( $this->output( 'where httpd' ) ) );
					foreach ( preg_split( '/\r?\n/', $apachePath ) as $candidate ) {
						if ( is_file( $candidate ) ) { $apacheBin = $candidate; break; }
					}
				} elseif ( $os === 'Darwin' ) {
					$macPaths = [
						'/Applications/MAMP/Library/bin/httpd',
						trim( (string) ( $this->output( 'which httpd' ) ) ),
					];
					foreach ( $macPaths as $path ) {
						if ( ! empty( $path ) && is_file( $path ) ) {
							$apacheBin = $path;
							break;
						}
					}
				} else {
					$linuxPaths = [
						trim( (string) ( $this->output( 'command -v apachectl 2>/dev/null' ) ) ),
						trim( (string) ( $this->output( 'command -v httpd 2>/dev/null' ) ) ),
					];
					foreach ( $linuxPaths as $path ) {
						if ( ! empty( $path ) && is_file( $path ) ) {
							$apacheBin = $path;
							break;
						}
					}
				}
			}

			if ( ! empty( $apacheBin ) ) {
				if ( $this->os === 'Windows' && strpbrk( $apacheBin, "%!\r\n" ) !== false ) { throw new \RuntimeException( 'Unsupported command path.' ); }
				$apacheVersion = $this->output( escapeshellarg( $apacheBin ) . ' -v' );
			}

		} catch ( \Throwable $error ) { $apacheVersion = ''; }
		if ( preg_match( '/Server version: Apache\/([\d.]+)/', $apacheVersion, $matches ) ) {
			return [ 'status' => 'available', 'version' => $matches[1] ];
		}
		return [ 'status' => stripos( $this->software, 'Apache' ) !== false ? 'unknown' : 'unavailable', 'version' => '' ];
	}
}
