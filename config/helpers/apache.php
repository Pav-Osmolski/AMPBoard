<?php
/**
 * Apache Inspector, Toggle Apache and Virtual Hosts Manager helpers
 *
 * Helper functions for inspecting Apache: detecting binaries/config, versions, uptime,
 * virtual hosts, environment variables, and related diagnostics.
 *
 * @author  Pawel Osmolski
 * @version 1.2
 */

/**
 * Execute a shell command with fallbacks.
 *
 * @param string $cmd
 *
 * @return string|null
 */
function tryShell( string $cmd ): ?string {
	if ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', explode( ',', ini_get( 'disable_functions' ) ?? '' ) ) ) {
		return safe_shell_exec( "$cmd 2>&1" );
	}
	if ( function_exists( 'proc_open' ) && ! in_array( 'proc_open', explode( ',', ini_get( 'disable_functions' ) ?? '' ) ) ) {
		$descriptorspec = [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
		$process        = safe_shell_exec( $cmd, $descriptorspec, $pipes );
		if ( is_resource( $process ) ) {
			$output = stream_get_contents( $pipes[1] );
			fclose( $pipes[1] );
			proc_close( $process );

			return $output;
		}
	}

	return null;
}

/**
 * Check if the current PHP environment is running under Apache.
 *
 * @return bool
 */
function isApache(): bool {
	return (
		strpos( $_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache' ) !== false ||
		php_sapi_name() === 'apache2handler' ||
		function_exists( 'apache_get_version' )
	);
}

/**
 * Retrieve the Apache version from various sources.
 *
 * @return string
 */
function getApacheVersion(): string {
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

/**
 * Attempt to detect the Apache binary path.
 *
 * @return string|null
 */
function detectApacheBinary(): ?string {
	$paths = [
		// User defined
		APACHE_PATH,

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
		if ( file_exists( $p ) && is_executable( $p ) ) {
			return $p;
		}
	}

	// Fallback via shell
	foreach ( [ 'which apache2', 'which httpd', 'where httpd' ] as $cmd ) {
		$out = tryShell( $cmd );
		if ( $out && file_exists( trim( $out ) ) ) {
			return trim( $out );
		}
	}

	return null;
}

/**
 * Extract the Apache config file path from the `-V` output.
 *
 * @param string $binary
 *
 * @return string|null
 */
function getApacheConfigPath( string $binary ): ?string {
	$out = tryShell( "$binary -V" );
	if ( preg_match( '/SERVER_CONFIG_FILE="([^"]+)"/', $out, $conf ) ) {
		$file = $conf[1];
		if ( preg_match( '/HTTPD_ROOT="([^"]+)"/', $out, $root ) ) {
			return rtrim( $root[1], '/' ) . '/' . ltrim( $file, '/' );
		}

		return $file;
	}

	return null;
}

/**
 * Parse Include/IncludeOptional directives from a config file.
 *
 * @param string $conf
 *
 * @return array<int, string>
 */
function getIncludes( string $conf ): array {
	if ( ! file_exists( $conf ) ) {
		return [];
	}
	$lines  = file( $conf );
	$result = [];
	foreach ( $lines as $line ) {
		if ( preg_match( '/^\s*Include(?:Optional)?\s+(.+)/i', $line, $m ) ) {
			$result[] = trim( $m[1] );
		}
	}

	return $result;
}

/**
 * Estimate Apache uptime cross-platform.
 *
 * @param string $os
 *
 * @return string
 */
function getApacheUptimeEstimate( string $os ): string {
	if ( $os === 'Windows' ) {
		$output = tryShell( 'wmic process where "name=\'httpd.exe\'" get CreationDate /value' );
		if ( preg_match( '/CreationDate=(\d{14})/', $output, $match ) ) {
			$start = \DateTime::createFromFormat( 'YmdHis', substr( $match[1], 0, 14 ) );
			if ( $start ) {
				$diff = ( new \DateTime() )->getTimestamp() - $start->getTimestamp();

				return formatDuration( $diff );
			}
		}
	} else {
		$output = tryShell( 'ps -eo etimes,comm | grep -E "apache|httpd" | head -n1' );
		if ( preg_match( '/^\s*(\d+)/', $output, $match ) ) {
			return formatDuration( (int) $match[1] );
		}
	}

	return 'Unavailable';
}

/**
 * Format a duration in seconds into a human-readable H:M:S string.
 *
 * @param int $seconds
 *
 * @return string
 */
function formatDuration( int $seconds ): string {
	$hours     = floor( $seconds / 3600 );
	$minutes   = floor( ( $seconds % 3600 ) / 60 );
	$remaining = $seconds % 60;

	return "{$seconds} seconds ({$hours}h {$minutes}m {$remaining}s)";
}

/**
 * Get the output of `-S` for virtual host listing, if available.
 *
 * @param string $binary
 *
 * @return string|null
 */
function getVirtualHosts( string $binary ): ?string {
	foreach ( [ $binary, 'apachectl', 'httpd' ] as $tool ) {
		$out = tryShell( "$tool -S" );
		if ( $out && stripos( $out, 'VirtualHost' ) !== false ) {
			return trim( $out );
		}
	}

	return null;
}

/**
 * Detect if Apache SAPI is in use via phpinfo() output.
 *
 * @return string|null
 */
function detectApacheSAPI(): ?string {
	ob_start();
	phpinfo( INFO_MODULES );
	$data = ob_get_clean();
	if ( strpos( $data, 'apache2handler' ) !== false ) {
		return 'apache2handler (Apache SAPI)';
	}

	return null;
}

/**
 * Get Apache-related environment variables from getenv and optionally /proc/self/environ.
 *
 * @return array<string, string>
 */
function getApacheEnvVars(): array {
	$envVars = [];
	foreach ( [ 'APACHE_RUN_DIR', 'APACHE_PID_FILE', 'APACHE_LOCK_DIR', 'INVOCATION_ID' ] as $var ) {
		$val = getenv( $var );
		if ( $val ) {
			$envVars[ $var ] = $val;
		}
	}
	if ( ! $GLOBALS['fastMode'] ) {
		$procPath = '/proc/self/environ';
		if ( file_exists( $procPath ) && is_readable( $procPath ) ) {
			$data  = file_get_contents( $procPath );
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

/**
 * Get loaded PHP .ini file info.
 *
 * @return array<string, string>
 */
function getIniFilesInfo(): array {
	return [
		'Loaded php.ini'     => obfuscate_value( php_ini_loaded_file() ) ?: 'N/A',
		'Scanned .ini files' => php_ini_scanned_files() ?: 'N/A'
	];
}

/**
 * Execute a shell command and return combined output and success state.
 *
 * Uses exec() to run the given command with stderr redirected to stdout so that
 * both standard output and error output are captured. Returns a structured array
 * containing the output (as a newline-joined string) and a boolean indicating
 * whether the command exit status was zero (success).
 *
 * @param string $cmd The command to execute.
 *
 * @return array{output:string,success:bool} Returns an associative array with:
 *                                           - output: combined output from the command
 *                                           - success: true if the command returned exit code 0
 */
function runCommand( string $cmd ): array {
	exec( $cmd . ' 2>&1', $output, $return_var );

	return [ 'output' => implode( "\n", $output ), 'success' => $return_var === 0 ];
}

/**
 * Determine a suitable Apache restart command for the given OS and detected paths.
 *
 * Attempts to construct the most appropriate command to restart Apache based on:
 * - Operating system (`Windows`, `Darwin`, `Linux`)
 * - Presence of a known Apache installation path
 * - Whether httpd.exe or Apache services are currently running
 * - Availability of MAMP/AMPPS/XAMPP utilities or standard apachectl/httpd binaries
 *
 * The returned string is intended to be executed via shell to trigger a restart.
 * If no suitable restart mechanism is detected, an empty string is returned.
 *
 * @param string $action Currently unused but reserved for future behaviour.
 * @param string $os The detected operating system family name.
 * @param string|null $apachePath Absolute path to a known Apache installation, if detected.
 *
 * @return string A restart command appropriate for the environment, or an empty string if none found.
 */
function findDefaultCommand( $action, $os, $apachePath ): string {
	switch ( $os ) {
		case 'Windows':
		{
			if ( $apachePath ) {
				$httpdPath = $apachePath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'httpd.exe';

				// Check if XAMPP httpd.exe is running
				$isHttpdRunning = false;
				$taskList       = safe_shell_exec( 'tasklist /FI "IMAGENAME eq httpd.exe"' );
				if ( strpos( $taskList, 'httpd.exe' ) !== false ) {
					$isHttpdRunning = true;
				}

				if ( $isHttpdRunning && file_exists( $httpdPath ) ) {
					return "start /B \"ApacheRestart\" \"$httpdPath\" -k restart";
				}

				// Fallback: check if Apache service is running
				$serviceStatus = safe_shell_exec( 'sc query Apache2.4' );
				if ( strpos( $serviceStatus, 'RUNNING' ) !== false ) {
					return "net stop Apache2.4 && net start Apache2.4";
				}

				// Optional: fallback to batch files
				$stopBat  = $apachePath . DIRECTORY_SEPARATOR . 'apache_stop.bat';
				$startBat = $apachePath . DIRECTORY_SEPARATOR . 'apache_start.bat';
				if ( file_exists( $stopBat ) && file_exists( $startBat ) ) {
					return "\"$stopBat\" && \"$startBat\"";
				}
			}

			// No known Apache instance running
			return '';
		}

		case 'Darwin':
		{
			// 1. Attempt to detect running Apache binary
			$apacheBinary = trim( safe_shell_exec( "ps -eo comm,args | grep -E 'httpd|apache2' | grep -v grep | awk '{print $1}' | head -n 1" ) );
			if ( ! empty( $apacheBinary ) && file_exists( $apacheBinary ) ) {
				return "sudo $apacheBinary -k restart";
			}

			// 2. MAMP-specific apachectl
			if ( file_exists( '/Applications/MAMP/Library/bin/apachectl' ) ) {
				return 'sudo /Applications/MAMP/Library/bin/apachectl restart';
			}

			// 3. Fallback to standard apachectl
			if ( file_exists( '/usr/sbin/apachectl' ) ) {
				return 'sudo /usr/sbin/apachectl restart';
			}

			return 'sudo apachectl restart'; // final fallback
		}

		case 'Linux':
		{
			// 1. Detect currently running Apache binary
			$apacheBinary = trim( safe_shell_exec( "ps -eo comm,args | grep -E 'httpd|apache2' | grep -v grep | awk '{print $1}' | head -n 1" ) );
			if ( ! empty( $apacheBinary ) && file_exists( $apacheBinary ) ) {
				return "sudo $apacheBinary -k restart";
			}

			// 2. Common Apache restart tools
			if ( file_exists( '/usr/sbin/apache2ctl' ) ) {
				return 'sudo /usr/sbin/apache2ctl restart';
			}

			if ( file_exists( '/usr/sbin/apachectl' ) ) {
				return 'sudo /usr/sbin/apachectl restart';
			}

			// 3. Fallback to systemd
			return 'sudo systemctl restart apache2';
		}

		default:
			return '';
	}
}

/**
 * Merge a parsed <VirtualHost> block into the $serverData map.
 *
 * @param array<string, array<string, mixed>> $serverData Accumulator keyed by vhost name.
 * @param array<string, mixed>|null $block Parsed vhost block to merge.
 *
 * @return void
 */
function collectServerBlock( array &$serverData, ?array $block ): void {
	if ( ! is_array( $block ) || empty( $block['name'] ) ) {
		return;
	}
	$name = $block['name'];
	if ( isset( $serverData[ $name ] ) ) {
		$serverData[ $name ]['_duplicate'] = true;
		$block['_duplicate']               = true;
	}
	$serverData[ $name ] = array_merge( [
		'valid'     => false,
		'cert'      => '',
		'key'       => '',
		'certValid' => true,
		'docRoot'   => '',
	], $block );
}

/**
 * Parse the system hosts file(s) into a flat, lowercased list of hostnames.
 *
 * @return array<int, string>
 */
function getHostsEntries(): array {
	$hostsFiles = [
		'Windows' => getenv( 'WINDIR' ) ? getenv( 'WINDIR' ) . '/System32/drivers/etc/hosts' : '',
		'Linux'   => '/etc/hosts',
		'Mac'     => '/etc/hosts',
	];

	$entries = [];

	foreach ( $hostsFiles as $path ) {
		if ( ! $path || ! file_exists( $path ) ) {
			continue;
		}

		foreach ( file( $path ) as $line ) {
			$line = trim( $line );
			if ( $line === '' || strpos( $line, '#' ) === 0 ) {
				continue;
			}

			$parts = preg_split( '/\s+/', $line );
			if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
				continue;
			}

			for ( $i = 1; $i < count( $parts ); $i ++ ) {
				$host = strtolower( trim( (string) $parts[ $i ] ) );
				if ( $host !== '' ) {
					$entries[] = $host;
				}
			}
		}
	}

	return array_values( array_unique( $entries ) );
}

/**
 * Parse Apache httpd-vhosts.conf into server blocks keyed by ServerName.
 *
 * Output structure mirrors what vhosts_manager.php already expects.
 *
 * @param string|null $vhostsPath Optional override for the vhosts file path.
 * @param string|null $crtPath Optional override for the crt directory.
 *
 * @return array<string, array<string, mixed>>
 */
function parseVhostsConf( ?string $vhostsPath = null, ?string $crtPath = null ): array {
	if ( ! defined( 'APACHE_PATH' ) || ! APACHE_PATH ) {
		return [];
	}

	$vhostsPath = $vhostsPath ?: APACHE_PATH . '/conf/extra/httpd-vhosts.conf';
	$crtPath    = $crtPath ?: APACHE_PATH . '/crt/';

	if ( ! file_exists( $vhostsPath ) ) {
		return [];
	}

	$lines        = file( $vhostsPath );
	$currentBlock = null;
	$serverData   = [];

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( preg_match( '#^<VirtualHost\s+.*:(\d+)>#i', $line, $matches ) ) {
			$port         = $matches[1];
			$currentBlock = [ 'ssl' => $port === '443' ];
		} elseif ( preg_match( '#^</VirtualHost>#i', $line ) ) {
			collectServerBlock( $serverData, $currentBlock );
			$currentBlock = null;
		} elseif ( is_array( $currentBlock ) ) {
			if ( preg_match( '/^\s*ServerName\s+(.+)/i', $line, $matches ) ) {
				$currentBlock['name'] = trim( $matches[1] );
			} elseif ( preg_match( '/^\s*DocumentRoot\s+(.+)/i', $line, $matches ) ) {
				$currentBlock['docRoot'] = trim( $matches[1] );
			} elseif ( preg_match( '/^\s*SSLCertificateFile\s+(.+)/i', $line, $matches ) ) {
				$currentBlock['cert'] = trim( $matches[1] );
			} elseif ( preg_match( '/^\s*SSLCertificateKeyFile\s+(.+)/i', $line, $matches ) ) {
				$currentBlock['key'] = trim( $matches[1] );
			}
		}
	}

	// Catch final block if file ends without </VirtualHost>
	collectServerBlock( $serverData, $currentBlock );

	// Normalise cert/key to APACHE_PATH/crt/{servername}/server.crt|key, just like vhosts_manager.
	foreach ( $serverData as $name => &$info ) {
		if ( ! empty( $info['ssl'] ) ) {
			$certPath = $crtPath . $name . '/server.crt';
			$keyPath  = $crtPath . $name . '/server.key';

			$info['cert']      = realpath( $certPath ) ?: str_replace( '/', DIRECTORY_SEPARATOR, $certPath );
			$info['key']       = realpath( $keyPath ) ?: str_replace( '/', DIRECTORY_SEPARATOR, $keyPath );
			$info['certValid'] = file_exists( $info['cert'] ) && file_exists( $info['key'] );
		}
	}
	unset( $info );

	return $serverData;
}

/**
 * Get vhost server data with validity applied from hosts file(s).
 *
 * Cached for repeated calls across modules (folders.php, vhosts_manager.php, etc.).
 *
 * @return array<string, array<string, mixed>>
 */
function getVhostServerData(): array {
	static $cache = null;

	if ( $cache !== null ) {
		return $cache;
	}

	$cache = parseVhostsConf();
	$hosts = getHostsEntries();

	if ( empty( $cache ) || empty( $hosts ) ) {
		return $cache;
	}

	foreach ( array_keys( $cache ) as $serverName ) {
		$lowerName = strtolower( $serverName );

		if ( in_array( $lowerName, $hosts, true ) ) {
			$cache[ $serverName ]['valid'] = true;
		}
	}

	return $cache;
}

/**
 * Get a map of valid vhost hostnames (lowercased => true).
 *
 * @return array<string, bool>
 */
function getValidVhostHostnames(): array {
	$valid = [];

	foreach ( getVhostServerData() as $name => $info ) {
		if ( ! empty( $info['valid'] ) ) {
			$valid[ strtolower( $name ) ] = true;
		}
	}

	return $valid;
}

/**
 * Check if a given hostname appears as a valid vhost + hosts entry.
 *
 * @param string $hostname Hostname (no scheme; e.g. "local.example.com").
 *
 * @return bool
 */
function isValidVhostHost( string $hostname ): bool {
	$hostname = strtolower( trim( $hostname ) );
	if ( $hostname === '' ) {
		return false;
	}

	$valid = getValidVhostHostnames();

	return isset( $valid[ $hostname ] );
}
