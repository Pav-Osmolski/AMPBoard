<?php
namespace AMPBoard\Database;

/** Read-only diagnostics. Each inspection owns and closes the supplied connection. */
final class Inspector {
	private \Closure $connect;
	private string $clientVersion;

	public function __construct( callable $connect, string $clientVersion ) {
		$this->connect = \Closure::fromCallable( $connect );
		$this->clientVersion = $clientVersion;
	}

	/** Null sections are unavailable; empty arrays are successful empty results. */
	public function inspect( bool $fastMode ): array {
		$connection = null;
		$report = [ 'error' => null, 'fastMode' => $fastMode, 'clientVersion' => $this->clientVersion ];
		try {
			$connection = ( $this->connect )();
			if ( ! empty( $connection->connect_error ) ) {
				throw new \RuntimeException( $connection->connect_error );
			}
			$report['serverVersion'] = (string) $connection->server_info;
			$report['hostInfo'] = (string) $connection->host_info;
			$user = $this->rows( $connection, 'SELECT USER()', true );
			$report['user'] = (string) ( $user[0][0] ?? 'Unknown' );
			$databases = $this->rows( $connection, 'SHOW DATABASES', true );
			$report['databases'] = $databases === null ? null : [];
			foreach ( $databases ?? [] as $row ) {
				$name = (string) $row[0];
				if ( in_array( $name, [ 'information_schema', 'performance_schema', 'mysql', 'sys' ], true ) ) { continue; }
				$size = null;
				if ( ! $fastMode ) {
					// Names come from MySQL, but still require identifier quoting.
					$tables = $this->rows( $connection, 'SHOW TABLE STATUS FROM `' . str_replace( '`', '``', $name ) . '`' );
					if ( $tables !== null ) {
						$bytes = 0;
						foreach ( $tables as $table ) { $bytes += (float) ( $table['Data_length'] ?? 0 ) + (float) ( $table['Index_length'] ?? 0 ); }
						$size = round( $bytes / 1024 / 1024, 2 );
					}
				}
				$report['databases'][] = [ 'name' => $name, 'sizeMb' => $size ];
			}
			$report['variables'] = $this->rows( $connection, "SHOW VARIABLES LIKE 'version%'" );
			$report['status'] = $this->rows( $connection, "SHOW STATUS LIKE 'Uptime'" );
			$report['processes'] = $this->rows( $connection, 'SHOW FULL PROCESSLIST' );
		} catch ( \Throwable $error ) {
			$report['error'] = $error->getMessage();
		} finally {
			if ( $connection !== null ) { $connection->close(); }
		}
		return $report;
	}

	private function rows( object $connection, string $sql, bool $numeric = false ): ?array {
		$result = null;
		try {
			// Report modes may return false, emit a warning, or throw. Do not alter the caller's mode.
			$result = @$connection->query( $sql );
			if ( ! is_object( $result ) ) { return null; }
			$rows = [];
			while ( true ) {
				$row = $numeric ? $result->fetch_row() : $result->fetch_assoc();
				if ( $row === null ) { return $rows; }
				if ( $row === false ) { return null; }
				$rows[] = $row;
			}
		} catch ( \Throwable $error ) {
			return null;
		} finally {
			if ( is_object( $result ) ) { $result->free(); }
		}
	}
}
