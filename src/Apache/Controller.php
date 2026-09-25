<?php

namespace AMPBoard\Apache;

use Closure;

/** Selects the existing platform restart strategy; executes only on explicit restart(). */
final class Controller {
	private string $apachePath;
	private string $os;
	private CommandRunner $commands;
	private Closure $exists;

	public function __construct( string $apachePath, string $os, CommandRunner $commands, ?callable $exists = null ) {
		$this->apachePath = rtrim( $apachePath, '/\\' );
		$this->os = $os;
		$this->commands = $commands;
		$this->exists = Closure::fromCallable( $exists ?? 'is_file' );
	}

	private function output( string $command ): string { return $this->commands->run( $command )['output']; }

	private function quote( string $path ): string {
		if ( $this->os !== 'Windows' ) { return escapeshellarg( $path ); }
		// cmd expands these characters even inside quotes; reject ambiguous configured paths.
		if ( strpbrk( $path, "\"%!?\r\n" ) !== false ) { throw new \RuntimeException( 'Unsupported Apache command path.' ); }
		return '"' . $path . '"';
	}

	public function restart(): array {
		$command = $this->restartCommand();
		if ( $command === '' ) { return [ 'success' => false, 'message' => 'Unable to determine command' ]; }
		$result = $this->commands->run( $command );
		return [ 'success' => $result['success'],
			'message' => $result['success'] ? 'Apache restart command executed successfully.' : 'Failed to restart Apache.',
			'output' => $result['output'] ];
	}

	public function restartCommand(): string {
		$os = $this->os;
		$apachePath = $this->apachePath;
		switch ( $os ) {
			case 'Windows':
			{
				if ( $apachePath ) {
					$httpdPath = $apachePath . '/bin/httpd.exe';

					// Check if XAMPP httpd.exe is running
					$isHttpdRunning = false;
					$taskList       = $this->output( 'tasklist /FI "IMAGENAME eq httpd.exe"' );
					if ( strpos( $taskList, 'httpd.exe' ) !== false ) {
						$isHttpdRunning = true;
					}

					if ( $isHttpdRunning && ($this->exists)( $httpdPath ) ) {
						return 'start /B "ApacheRestart" ' . $this->quote( $httpdPath ) . ' -k restart';
					}

					// Fallback: check if Apache service is running
					$serviceStatus = $this->output( 'sc query Apache2.4' );
					if ( strpos( $serviceStatus, 'RUNNING' ) !== false ) {
						return "net stop Apache2.4 && net start Apache2.4";
					}

					// Optional: fallback to batch files
					$stopBat  = $apachePath . '/apache_stop.bat';
					$startBat = $apachePath . '/apache_start.bat';
					if ( ($this->exists)( $stopBat ) && ($this->exists)( $startBat ) ) {
						return $this->quote( $stopBat ) . ' && ' . $this->quote( $startBat );
					}
				}

				// No known Apache instance running
				return '';
			}

			case 'Darwin':
			{
				// 1. Attempt to detect running Apache binary
				$apacheBinary = trim( $this->output( "ps -eo comm,args | grep -E 'httpd|apache2' | grep -v grep | awk '{print $1}' | head -n 1" ) );
				if ( ! empty( $apacheBinary ) && ($this->exists)( $apacheBinary ) ) {
					return 'sudo ' . $this->quote( $apacheBinary ) . ' -k restart';
				}

				// 2. MAMP-specific apachectl
				if ( ($this->exists)( '/Applications/MAMP/Library/bin/apachectl' ) ) {
					return 'sudo /Applications/MAMP/Library/bin/apachectl restart';
				}

				// 3. Fallback to standard apachectl
				if ( ($this->exists)( '/usr/sbin/apachectl' ) ) {
					return 'sudo /usr/sbin/apachectl restart';
				}

				return 'sudo apachectl restart'; // final fallback
			}

			case 'Linux':
			{
				// 1. Detect currently running Apache binary
				$apacheBinary = trim( $this->output( "ps -eo comm,args | grep -E 'httpd|apache2' | grep -v grep | awk '{print $1}' | head -n 1" ) );
				if ( ! empty( $apacheBinary ) && ($this->exists)( $apacheBinary ) ) {
					return 'sudo ' . $this->quote( $apacheBinary ) . ' -k restart';
				}

				// 2. Common Apache restart tools
				if ( ($this->exists)( '/usr/sbin/apache2ctl' ) ) {
					return 'sudo /usr/sbin/apache2ctl restart';
				}

				if ( ($this->exists)( '/usr/sbin/apachectl' ) ) {
					return 'sudo /usr/sbin/apachectl restart';
				}

				// 3. Fallback to systemd
				return 'sudo systemctl restart apache2';
			}

			default:
				return '';
		}
	}
}
