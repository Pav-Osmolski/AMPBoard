<?php

namespace AMPBoard\Apache;

/** Read-only Apache diagnostics with explicit paths, command execution, and fast mode. */
final class Inspector {
	private string $apachePath;
	private CommandRunner $commands;
	private bool $fastMode;
	private string $procEnvironment;

	public function __construct( string $apachePath, CommandRunner $commands, bool $fastMode = false, string $procEnvironment = '/proc/self/environ' ) {
		$this->apachePath = rtrim( $apachePath, '/\\' );
		$this->commands = $commands;
		$this->fastMode = $fastMode;
		$this->procEnvironment = $procEnvironment;
	}

	private function output( string $command ): string {
		return $this->commands->run( $command )['output'];
	}

	private static function absolutePath( string $path ): bool {
		return str_starts_with( $path, '/' ) || str_starts_with( $path, '\\' ) || preg_match( '/^[A-Za-z]:[\\\\\/]/', $path ) === 1;
	}

	public function isApache(): bool {
		return (
			strpos( $_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache' ) !== false ||
			php_sapi_name() === 'apache2handler' ||
			function_exists( 'apache_get_version' )
		);
	}

	public function getApacheVersion(): string {
		if ( function_exists( 'apache_get_version' ) ) {
			return "via apache_get_version: " . apache_get_version();
		}
		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
			return "via SERVER_SOFTWARE: " . $_SERVER['SERVER_SOFTWARE'];
		}
		ob_start();
		phpinfo( INFO_MODULES );
		$data = ob_get_clean();
		if ( preg_match( '/Apache\/[\d.]+/', $data, $m ) ) {
			return "via phpinfo: " . $m[0];
		}

		return "not detected";
	}

	public function detectApacheBinary(): ?string {
		$paths = [
			// User defined
			$this->apachePath,
			$this->apachePath . '/bin/httpd.exe',
			$this->apachePath . '/bin/httpd',

			// Linux
			'/usr/sbin/apache2',
			'/usr/sbin/httpd',
			'/usr/local/apache2/bin/httpd',
			'/opt/lampp/bin/httpd',
			'/usr/libexec/apache2/httpd',
			'/usr/local/sbin/httpd',
			'/snap/bin/httpd',

			// Linuxbrew
			'/home/linuxbrew/.linuxbrew/bin/httpd',
			'/home/linuxbrew/.linuxbrew/opt/httpd/bin/httpd',

			// macOS (Homebrew)
			'/opt/homebrew/bin/httpd',
			'/opt/homebrew/sbin/httpd',
			'/opt/homebrew/opt/httpd/bin/httpd',

			// macOS (MAMP)
			'/Applications/MAMP/Library/bin/httpd',

			// macOS (AMPPS)
			'/Applications/AMPPS/apache/bin/httpd',

			// Windows (XAMPP + AMPPS)
			'C:\\xampp\\apache\\bin\\httpd.exe',
			'C:\\Program Files\\Apache Group\\Apache2\\bin\\httpd.exe',
			'C:\\Apache24\\bin\\httpd.exe',
			'C:\\Program Files (x86)\\Ampps\\apache\\bin\\httpd.exe',
			'C:\\Program Files\\Ampps\\apache\\bin\\httpd.exe'
		];

		foreach ( $paths as $p ) {
			if ( is_file( $p ) && is_executable( $p ) ) {
				return $p;
			}
		}

		// Fallback via shell
		foreach ( [ 'which apache2', 'which httpd', 'where httpd' ] as $cmd ) {
			$out = $this->output( $cmd );
			foreach ( preg_split( '/\r?\n/', trim( $out ) ) as $candidate ) {
				if ( is_file( $candidate ) && is_executable( $candidate ) ) { return $candidate; }
			}
		}

		return null;
	}

	public function getApacheConfigPath( string $binary ): ?string {
		if ( $this->fastMode ) { return null; }
		$out = $this->output( escapeshellarg( $binary ) . ' -V' );
		if ( preg_match( '/SERVER_CONFIG_FILE="([^"]+)"/', $out, $conf ) ) {
			$file = $conf[1];
			if ( preg_match( '/HTTPD_ROOT="([^"]+)"/', $out, $root ) ) {
				return self::absolutePath( $file ) ? $file : rtrim( $root[1], '/\\' ) . '/' . ltrim( $file, '/' );
			}

			return $file;
		}

		return null;
	}

	public function getIncludes( string $conf ): array {
		if ( ! is_readable( $conf ) ) {
			return [];
		}
		$lines  = @file( $conf ) ?: [];
		$result = [];
		foreach ( $lines as $line ) {
			if ( preg_match( '/^\s*Include(?:Optional)?\s+(.+)/i', $line, $m ) ) {
				$result[] = trim( $m[1] );
			}
		}

		return $result;
	}

	public function getApacheUptimeEstimate( string $os ): string {
		if ( $this->fastMode ) { return 'Unavailable'; }
		if ( $os === 'Windows' ) {
			$output = $this->output( 'wmic process where "name=\'httpd.exe\'" get CreationDate /value' );
			if ( preg_match( '/CreationDate=(\d{14})/', $output, $match ) ) {
				$start = \DateTime::createFromFormat( 'YmdHis', substr( $match[1], 0, 14 ) );
				if ( $start ) {
					$diff = ( new \DateTime() )->getTimestamp() - $start->getTimestamp();

					return $this->formatDuration( $diff );
				}
			}
		} else {
			$output = $this->output( 'ps -eo etimes,comm | grep -E "apache|httpd" | head -n1' );
			if ( preg_match( '/^\s*(\d+)/', $output, $match ) ) {
				return $this->formatDuration( (int) $match[1] );
			}
		}

		return 'Unavailable';
	}

	public function formatDuration( int $seconds ): string {
		$hours     = floor( $seconds / 3600 );
		$minutes   = floor( ( $seconds % 3600 ) / 60 );
		$remaining = $seconds % 60;

		return "{$seconds} seconds ({$hours}h {$minutes}m {$remaining}s)";
	}

	public function getVirtualHosts( string $binary ): ?string {
		if ( $this->fastMode ) { return null; }
		foreach ( [ $binary, 'apachectl', 'httpd' ] as $tool ) {
			$out = $this->output( escapeshellarg( $tool ) . ' -S' );
			if ( $out && stripos( $out, 'VirtualHost' ) !== false ) {
				return trim( $out );
			}
		}

		return null;
	}

	public function detectApacheSAPI(): ?string {
		ob_start();
		phpinfo( INFO_MODULES );
		$data = ob_get_clean();
		if ( strpos( $data, 'apache2handler' ) !== false ) {
			return 'apache2handler (Apache SAPI)';
		}

		return null;
	}

	public function getApacheEnvVars(): array {
		$envVars = [];
		foreach ( [ 'APACHE_RUN_DIR', 'APACHE_PID_FILE', 'APACHE_LOCK_DIR', 'INVOCATION_ID' ] as $var ) {
			$val = getenv( $var );
			if ( $val ) {
				$envVars[ $var ] = $val;
			}
		}
		if ( ! $this->fastMode ) {
			$procPath = $this->procEnvironment;
			if ( file_exists( $procPath ) && is_readable( $procPath ) ) {
				$data  = (string) @file_get_contents( $procPath );
				$pairs = explode( "\0", $data );
				foreach ( $pairs as $pair ) {
					if ( stripos( $pair, 'apache' ) !== false && strpos( $pair, '=' ) !== false ) {
						list( $k, $v ) = explode( '=', $pair, 2 );
						$envVars[ $k ] = $v;
					}
				}
			}
		}

		return $envVars;
	}

	public function getIniFilesInfo(): array {
		return [
			'Loaded php.ini'     => php_ini_loaded_file() ?: 'N/A',
			'Scanned .ini files' => php_ini_scanned_files() ?: 'N/A'
		];
	}
}
