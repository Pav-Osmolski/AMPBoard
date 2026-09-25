<?php

namespace AMPBoard\Apache;

/** Virtual-host data cached per configured installation, never in global state. */
final class VhostCatalog {
	private string $apachePath;
	private array $hostsFiles;
	private ?array $cache = null;

	public function __construct( string $apachePath, array $hostsFiles ) {
		$this->apachePath = rtrim( $apachePath, '/\\' );
		$this->hostsFiles = $hostsFiles;
	}

	public function path(): string { return $this->apachePath . '/conf/extra/httpd-vhosts.conf'; }

	private function collectServerBlock( array &$serverData, ?array $block ): void {
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

	public function getHostsEntries(): array {

		$entries = [];

		foreach ( $this->hostsFiles as $path ) {
			if ( ! $path || ! is_readable( $path ) ) {
				continue;
			}

			foreach ( ( @file( $path ) ?: [] ) as $line ) {
				$line = trim( explode( '#', $line, 2 )[0] );
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

	public function parseVhostsConf( ?string $vhostsPath = null, ?string $crtPath = null ): array {
		if ( $this->apachePath === '' && $vhostsPath === null ) {
			return [];
		}

		$vhostsPath = $vhostsPath ?: $this->path();
		$crtPath    = rtrim( $crtPath ?: $this->apachePath . '/crt', '/\\' ) . '/';

		if ( ! is_readable( $vhostsPath ) ) {
			return [];
		}

		$lines        = @file( $vhostsPath ) ?: [];
		$currentBlock = null;
		$serverData   = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( preg_match( '#^<VirtualHost\s+.*:(\d+)>#i', $line, $matches ) ) {
				$port         = $matches[1];
				$currentBlock = [ 'ssl' => $port === '443' ];
			} elseif ( preg_match( '#^</VirtualHost>#i', $line ) ) {
				$this->collectServerBlock( $serverData, $currentBlock );
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
		$this->collectServerBlock( $serverData, $currentBlock );

		// Retain the managed certificate convention under the configured Apache directory.
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

	public function getVhostServerData(): array {

		if ( $this->cache !== null ) {
			return $this->cache;
		}

		$this->cache = $this->parseVhostsConf();
		$hosts = $this->getHostsEntries();

		if ( empty( $this->cache ) || empty( $hosts ) ) {
			return $this->cache;
		}

		foreach ( array_keys( $this->cache ) as $serverName ) {
			$lowerName = strtolower( $serverName );

			if ( in_array( $lowerName, $hosts, true ) ) {
				$this->cache[ $serverName ]['valid'] = true;
			}
		}

		return $this->cache;
	}

	public function getValidVhostHostnames(): array {
		$valid = [];

		foreach ( $this->getVhostServerData() as $name => $info ) {
			if ( ! empty( $info['valid'] ) ) {
				$valid[ strtolower( $name ) ] = true;
			}
		}

		return $valid;
	}

	public function isValidVhostHost( string $hostname ): bool {
		$hostname = strtolower( trim( $hostname ) );
		if ( $hostname === '' ) {
			return false;
		}

		$valid = $this->getValidVhostHostnames();

		return isset( $valid[ $hostname ] );
	}
}
